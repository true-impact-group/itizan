<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\WeightPlan;

class WeightPlanPolicy
{
    public function view(User $user, WeightPlan $plan): bool
    {
        return $plan->user_id === $user->id
            || $user->hasRole(Role::Doctor)
            || $this->isAssignedDietitian($user, $plan);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Doctor);
    }

    public function update(User $user, WeightPlan $plan): bool
    {
        return $user->hasRole(Role::Doctor) || $this->isAssignedDietitian($user, $plan);
    }

    private function isAssignedDietitian(User $user, WeightPlan $plan): bool
    {
        return $user->hasRole(Role::Dietitian)
            && $plan->dietitian_id !== null
            && $user->practitioner?->id === $plan->dietitian_id;
    }
}
