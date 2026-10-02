<?php

namespace App\Actions\Medication;

use App\Actions\Support\RequestContact;
use App\Enums\ContactTarget;
use App\Enums\SideEffectStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\SideEffectReport;
use App\Models\User;
use App\Support\Audit;

class TriageSideEffect
{
    public function __construct(private readonly RequestContact $contacts) {}

    public function handle(SideEffectReport $report, User $handler, SideEffectStatus $status, ?string $note = null): SideEffectReport
    {
        if ($status === SideEffectStatus::New) {
            throw new BusinessRuleViolation(__('اختر حالة المتابعة.'));
        }

        $wasEscalated = $report->status === SideEffectStatus::Escalated;

        $report->update([
            'status' => $status,
            'handled_by' => $handler->id,
            'handled_at' => now(),
            'response_note' => $note,
        ]);

        // Escalating by the care team sends an urgent request to the doctor (once).
        if ($status === SideEffectStatus::Escalated && ! $wasEscalated) {
            $this->contacts->handle(
                $report->user,
                ContactTarget::Doctor,
                __('صعّد فريق الرعاية عَرَضًا جانبيًا يحتاج إلى مراجعة الطبيب.'),
                urgent: true,
                source: 'side_effect',
            );
        }

        Audit::log('side_effect.'.$status->value, $report);

        return $report;
    }
}
