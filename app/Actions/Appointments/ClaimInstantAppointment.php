<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Appointment;
use App\Models\Practitioner;
use App\Notifications\AppointmentNotification;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/** A doctor picks up an instant-consultation request from the queue. First doctor to claim wins. */
class ClaimInstantAppointment
{
    public function handle(Appointment $appointment, Practitioner $doctor): Appointment
    {
        if (! $doctor->isVerified() || ! $doctor->accepts_instant) {
            throw new BusinessRuleViolation(__('لا يمكنك استلام الاستشارات الفورية.'));
        }

        $claimed = DB::transaction(function () use ($appointment, $doctor) {
            $fresh = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($fresh->practitioner_id !== null || $fresh->status !== AppointmentStatus::Requested || ! $fresh->is_instant) {
                throw new BusinessRuleViolation(__('تم استلام هذه الاستشارة من طبيب آخر.'));
            }

            $fresh->update([
                'practitioner_id' => $doctor->id,
                'status' => AppointmentStatus::Confirmed,
                'scheduled_at' => now(),
            ]);

            return $fresh;
        });

        Audit::log('appointment.claimed', $claimed);
        $claimed->user->notify(new AppointmentNotification($claimed));

        return $claimed;
    }
}
