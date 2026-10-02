<?php

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Appointment;
use App\Support\Audit;

class CompleteAppointment
{
    public function handle(Appointment $appointment, AppointmentStatus $outcome, ?string $notes = null): Appointment
    {
        if ($appointment->status !== AppointmentStatus::Confirmed) {
            throw new BusinessRuleViolation(__('لا يمكن إنهاء هذا الموعد في حالته الحالية.'));
        }

        if (! in_array($outcome, [AppointmentStatus::Completed, AppointmentStatus::NoShow], true)) {
            throw new BusinessRuleViolation(__('نتيجة غير صالحة للموعد.'));
        }

        $appointment->update([
            'status' => $outcome,
            'completed_at' => now(),
            'practitioner_notes' => $notes,
        ]);

        Audit::log('appointment.'.$outcome->value, $appointment);

        return $appointment;
    }
}
