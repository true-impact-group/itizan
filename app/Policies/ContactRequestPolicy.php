<?php

namespace App\Policies;

use App\Enums\ContactTarget;
use App\Enums\Role;
use App\Models\ContactRequest;
use App\Models\User;

class ContactRequestPolicy
{
    public function view(User $user, ContactRequest $request): bool
    {
        return $request->user_id === $user->id || $this->canHandle($user, $request);
    }

    /** The care team handles everything; doctors handle requests addressed to them. */
    public function handle(User $user, ContactRequest $request): bool
    {
        return $this->canHandle($user, $request);
    }

    private function canHandle(User $user, ContactRequest $request): bool
    {
        return $user->hasRole(Role::CareTeam)
            || ($user->hasRole(Role::Doctor) && $request->target === ContactTarget::Doctor);
    }
}
