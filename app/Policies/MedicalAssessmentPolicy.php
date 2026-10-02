<?php

namespace App\Policies;

use App\Enums\AssessmentStatus;
use App\Enums\Role;
use App\Models\MedicalAssessment;
use App\Models\User;
use App\Models\WeightPlan;

/**
 * Clinical data: the client, doctors, and the client's own dietitian only.
 * Administrators and support staff deliberately cannot open it.
 */
class MedicalAssessmentPolicy
{
    public function view(User $user, MedicalAssessment $assessment): bool
    {
        if ($assessment->user_id === $user->id || $user->hasRole(Role::Doctor)) {
            return true;
        }

        return $user->hasRole(Role::Dietitian)
            && $user->practitioner !== null
            && WeightPlan::query()
                ->where('user_id', $assessment->user_id)
                ->where('dietitian_id', $user->practitioner->id)
                ->exists();
    }

    public function update(User $user, MedicalAssessment $assessment): bool
    {
        return $assessment->user_id === $user->id && $assessment->status->isEditableByClient();
    }

    public function review(User $user, MedicalAssessment $assessment): bool
    {
        return $user->hasRole(Role::Doctor) && $assessment->status === AssessmentStatus::Submitted;
    }
}
