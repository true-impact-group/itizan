<?php

namespace App\Policies;

use App\Enums\LabStatus;
use App\Enums\Role;
use App\Models\LabRequest;
use App\Models\User;

class LabRequestPolicy
{
    public function view(User $user, LabRequest $lab): bool
    {
        return $lab->user_id === $user->id || $user->hasRole(Role::Doctor);
    }

    public function upload(User $user, LabRequest $lab): bool
    {
        return $lab->user_id === $user->id && $lab->status !== LabStatus::Reviewed;
    }

    public function review(User $user, LabRequest $lab): bool
    {
        return $user->hasRole(Role::Doctor) && $lab->status === LabStatus::Uploaded;
    }
}
