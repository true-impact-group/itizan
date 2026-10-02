<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Appointment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelAppointment
{
    public function handle(Appointment $appointment, User $by, ?string $reason = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $by, $reason) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if (! $appointment->status->isActive()) {
                throw new BusinessRuleViolation(__('لا يمكن إلغاء هذا الموعد.'));
            }

            if ($by->isClient() && ! $appointment->isCancellable()) {
                throw new BusinessRuleViolation(__('لا يمكن إلغاء الموعد قبل بدايته بأقل من :hours ساعات. تواصل مع فريق الرعاية.', [
                    'hours' => config('itizan.booking.cancel_before_hours'),
                ]));
            }

            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // Give the included follow-up back so a cancelled visit does not use up the quota.
            if ($appointment->type === AppointmentType::FollowUp && $appointment->subscription_id) {
                Subscription::query()
                    ->whereKey($appointment->subscription_id)
                    ->where('followups_used', '>', 0)
                    ->decrement('followups_used');
            }

            return $appointment;
        });
    }
}
