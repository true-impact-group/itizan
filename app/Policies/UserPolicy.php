<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\WeightPlan;

/** Policy for viewing/managing other user accounts (client files, staff). */
class UserPolicy
{
    /** Account-level details (name, subscription): operational staff. */
    public function view(User $user, User $subject): bool
    {
        return $user->id === $subject->id
            || $user->hasRole(Role::Admin, Role::Support, Role::Doctor, Role::Dietitian, Role::CareTeam);
    }

    /** Clinical file (assessment, plan, prescriptions, measurements). Dietitians only for clients assigned to them. */
    public function viewClinical(User $user, User $subject): bool
    {
        if ($user->id === $subject->id) {
            return true;
        }

        if (! $subject->isClient()) {
            return false;
        }

        if ($user->hasRole(Role::Doctor, Role::CareTeam)) {
            return true;
        }

        return $user->hasRole(Role::Dietitian)
            && $user->practitioner !== null
            && WeightPlan::query()->where('user_id', $subject->id)->where('dietitian_id', $user->practitioner->id)->exists();
    }

    public function manage(User $user, User $subject): bool
    {
        return $user->hasRole(Role::Admin) && $user->id !== $subject->id;
    }
}
