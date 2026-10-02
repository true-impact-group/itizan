<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\SideEffectReport;
use App\Models\User;

class SideEffectReportPolicy
{
    public function view(User $user, SideEffectReport $report): bool
    {
        return $report->user_id === $user->id || $user->hasRole(Role::CareTeam, Role::Doctor);
    }

    public function handle(User $user, SideEffectReport $report): bool
    {
        return $user->hasRole(Role::CareTeam, Role::Doctor);
    }
}
