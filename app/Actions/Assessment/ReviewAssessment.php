<?php

namespace App\Actions\Assessment;

use App\Enums\AssessmentStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\MedicalAssessment;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Audit;

/** The doctor alone decides whether the programme is medically suitable. */
class ReviewAssessment
{
    private const DECISIONS = [AssessmentStatus::Approved, AssessmentStatus::NeedsInfo, AssessmentStatus::NotSuitable];

    public function handle(MedicalAssessment $assessment, User $doctor, AssessmentStatus $decision, ?string $notes = null): MedicalAssessment
    {
        if ($assessment->status !== AssessmentStatus::Submitted) {
            throw new BusinessRuleViolation(__('هذا التقييم ليس بانتظار المراجعة.'));
        }

        if (! in_array($decision, self::DECISIONS, true)) {
            throw new BusinessRuleViolation(__('قرار غير صالح.'));
        }

        if ($decision !== AssessmentStatus::Approved && blank($notes)) {
            throw new BusinessRuleViolation(__('اكتب ملاحظة توضح القرار للعميل.'));
        }

        $assessment->update([
            'status' => $decision,
            'reviewed_by' => $doctor->id,
            'reviewed_at' => now(),
            'reviewer_notes' => $notes,
        ]);

        Audit::log('assessment.reviewed', $assessment, ['decision' => $decision->value]);

        // Continuity of care: the approving doctor follows the client from now on (an existing choice is kept).
        $client = $assessment->user;
        if ($decision === AssessmentStatus::Approved && $client->primary_practitioner_id === null && $doctor->practitioner) {
            $client->forceFill(['primary_practitioner_id' => $doctor->practitioner->id])->save();
        }

        $assessment->user->notify(new GenericNotification(
            'تمت مراجعة تقييمك الطبي',
            'اطّلع على نتيجة المراجعة والخطوات التالية من حسابك.',
            route('account.assessment.show'),
        ));

        return $assessment;
    }
}
