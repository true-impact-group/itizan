<?php

namespace App\Actions\Appointments;

use App\Actions\Consent\RecordConsent;
use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\ConsentType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Appointment;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Services\SlotFinder;
use App\Support\FtmsMeeting;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class BookAppointment
{
    public function __construct(
        private readonly SlotFinder $slots,
        private readonly RecordConsent $consents,
    ) {}

    /**
     * @param  array{type: AppointmentType, mode: AppointmentMode, starts_at?: ?CarbonInterface, practitioner_id?: ?int, instant?: bool, notes?: ?string}  $data
     */
    public function handle(User $client, array $data): Appointment
    {
        $type = $data['type'];
        $instant = (bool) ($data['instant'] ?? false);

        if (! $instant && empty($data['starts_at'])) {
            throw new BusinessRuleViolation(__('اختر موعدًا متاحًا.'));
        }

        try {
            $appointment = DB::transaction(function () use ($client, $data, $type, $instant) {
                $subscription = $type === AppointmentType::FollowUp ? $this->consumeFollowUp($client) : null;

                if ($type === AppointmentType::FreeIntro) {
                    $this->guardFreeIntro($client);
                }

                $practitioner = null;
                if (! $instant) {
                    $practitioner = $this->slots->practitionerFor($data['starts_at'], $data['practitioner_id'] ?? null);

                    if ($practitioner === null) {
                        throw new BusinessRuleViolation(__('هذا الموعد لم يعد متاحًا، اختر موعدًا آخر.'));
                    }
                }

                $reference = FtmsMeeting::newReference();
                $confirmed = $practitioner !== null;

                $appointment = Appointment::create([
                    'user_id' => $client->id,
                    'practitioner_id' => $practitioner?->id,
                    'subscription_id' => $subscription?->id,
                    'type' => $type,
                    'mode' => $data['mode'],
                    'status' => $confirmed ? AppointmentStatus::Confirmed : AppointmentStatus::Requested,
                    'scheduled_at' => $instant ? now() : $data['starts_at'],
                    'is_instant' => $instant,
                    'duration_minutes' => (int) config('itizan.booking.default_slot_minutes'),
                    'ftms_reference' => $reference,
                    'meeting_url' => FtmsMeeting::urlFor($reference),
                    'client_notes' => $data['notes'] ?? null,
                ]);

                $this->consents->handle($client, ConsentType::Telemedicine);

                return $appointment;
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'appointments_one_free_intro_per_user')) {
                throw new BusinessRuleViolation(__('لقد استفدت من الاستشارة المجانية من قبل.'));
            }

            throw new BusinessRuleViolation(__('هذا الموعد لم يعد متاحًا، اختر موعدًا آخر.'));
        }

        if ($appointment->status === AppointmentStatus::Confirmed) {
            $client->notify(new AppointmentNotification($appointment));
        }

        return $appointment;
    }

    private function guardFreeIntro(User $client): void
    {
        $used = Appointment::query()
            ->where('user_id', $client->id)
            ->where('type', AppointmentType::FreeIntro->value)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->exists();

        if ($used) {
            throw new BusinessRuleViolation(__('لقد استفدت من الاستشارة المجانية من قبل.'));
        }
    }

    /** Reserves one included follow-up on the client's current subscription (row-locked against races). */
    private function consumeFollowUp(User $client): Subscription
    {
        $subscription = Subscription::query()
            ->where('user_id', $client->id)
            ->current()
            ->orderByDesc('ends_at')
            ->lockForUpdate()
            ->first();

        if ($subscription === null) {
            throw new BusinessRuleViolation(__('تحتاج إلى اشتراك فعّال لحجز استشارة متابعة.'));
        }

        if ($subscription->remainingFollowups() < 1) {
            throw new BusinessRuleViolation(__('استنفدت استشارات المتابعة المشمولة في باقتك.'));
        }

        $subscription->increment('followups_used');

        return $subscription;
    }
}
