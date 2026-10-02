<?php

namespace App\Actions\Labs;

use App\Enums\LabStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\LabRequest;
use App\Notifications\GenericNotification;
use App\Support\Audit;

class ReviewLabResult
{
    public function handle(LabRequest $lab, ?string $notes = null): LabRequest
    {
        if ($lab->status !== LabStatus::Uploaded) {
            throw new BusinessRuleViolation(__('لا توجد نتيجة مرفوعة لمراجعتها.'));
        }

        $lab->update(['status' => LabStatus::Reviewed, 'reviewed_at' => now(), 'reviewer_notes' => $notes]);

        Audit::log('lab.reviewed', $lab);
        $lab->user->notify(new GenericNotification(
            'تمت مراجعة نتيجة تحليلك',
            'اطّلع على ملاحظات الطبيب من حسابك.',
            route('account.labs.index'),
        ));

        return $lab;
    }
}
