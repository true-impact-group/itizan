<?php

namespace App\Actions\Medication;

use App\Enums\AssessmentStatus;
use App\Enums\PractitionerType;
use App\Enums\PrescriptionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\MedicalAssessment;
use App\Models\Practitioner;
use App\Models\Prescription;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Audit;
use Illuminate\Support\Carbon;

/**
 * Only a verified doctor can prescribe, and only for a client whose assessment the doctor approved.
 * The platform never creates prescriptions or changes doses on its own.
 */
class PrescribeMedication
{
    /**
     * @param  array{medication_name: string, dose: string, instructions?: ?string, interval_days: int, dose_time: string, starts_on: mixed, ends_on?: mixed, product_id?: ?int, pre_dose_questions?: list<array{text: string, alert_on: string}>, appointment_id?: ?int}  $data
     */
    public function handle(Practitioner $doctor, User $client, array $data): Prescription
    {
        if ($doctor->type !== PractitionerType::Doctor || ! $doctor->isVerified()) {
            throw new BusinessRuleViolation(__('فقط الطبيب الموثّق يمكنه كتابة الوصفات.'));
        }

        $approved = MedicalAssessment::query()
            ->where('user_id', $client->id)
            ->where('status', AssessmentStatus::Approved->value)
            ->exists();

        if (! $approved) {
            throw new BusinessRuleViolation(__('لا يمكن كتابة وصفة قبل اعتماد التقييم الطبي للعميل.'));
        }

        // Treatment is part of the paid programme: no subscription, no prescription (no exceptions).
        if (! $client->hasActiveSubscription()) {
            throw new BusinessRuleViolation(__('لا يمكن كتابة وصفة لعميل ليس لديه اشتراك فعّال.'));
        }

        $prescription = Prescription::create([
            'user_id' => $client->id,
            'practitioner_id' => $doctor->id,
            'appointment_id' => $data['appointment_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'medication_name' => $data['medication_name'],
            'dose' => $data['dose'],
            'instructions' => $data['instructions'] ?? null,
            'interval_days' => $data['interval_days'],
            'dose_time' => $data['dose_time'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?? null,
            'status' => PrescriptionStatus::Active,
            'pre_dose_questions' => array_values(array_filter(
                $data['pre_dose_questions'] ?? [],
                fn ($question) => filled($question['text'] ?? null),
            )) ?: null,
            'next_dose_at' => $this->firstDose($data['starts_on'], $data['dose_time'], (int) $data['interval_days']),
        ]);

        Audit::log('prescription.created', $prescription);

        $client->notify(new GenericNotification(
            'تمت إضافة وصفة جديدة إلى حسابك',
            'اطّلع على التفاصيل ومواعيد الجرعات من حسابك.',
            route('account.medications.show', $prescription),
        ));

        return $prescription;
    }

    /** First dose at or after now, following the schedule that starts on $startsOn. */
    private function firstDose(mixed $startsOn, string $time, int $intervalDays): Carbon
    {
        $dose = Carbon::parse(Carbon::parse($startsOn)->toDateString().' '.$time);

        while ($dose->lt(now())) {
            $dose->addDays(max($intervalDays, 1));
        }

        return $dose;
    }
}
