<?php

namespace App\Actions\Medication;

use App\Enums\PrescriptionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Prescription;
use App\Notifications\GenericNotification;
use App\Support\Audit;

class StopPrescription
{
    public function handle(Prescription $prescription, string $reason): Prescription
    {
        if (! $prescription->isActive()) {
            throw new BusinessRuleViolation(__('هذه الوصفة متوقفة بالفعل.'));
        }

        $prescription->update([
            'status' => PrescriptionStatus::Stopped,
            'stopped_at' => now(),
            'stop_reason' => $reason,
            'next_dose_at' => null,
        ]);

        Audit::log('prescription.stopped', $prescription);

        $prescription->user->notify(new GenericNotification(
            'تم إيقاف إحدى وصفاتك',
            'اطّلع على التفاصيل من حسابك وتواصل مع الطبيب عند الحاجة.',
            route('account.medications.show', $prescription),
        ));

        return $prescription;
    }
}
