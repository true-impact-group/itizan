<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    /** Lab results and symptom photos: the owner, doctors and the care team. */
    public function view(User $user, Attachment $attachment): bool
    {
        return $attachment->user_id === $user->id
            || $user->hasRole(Role::Doctor, Role::CareTeam);
    }
}
