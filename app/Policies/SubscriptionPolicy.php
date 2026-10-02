<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function view(User $user, Subscription $subscription): bool
    {
        return $subscription->user_id === $user->id || $user->hasRole(Role::Admin, Role::Support);
    }

    public function manage(User $user, Subscription $subscription): bool
    {
        return $subscription->user_id === $user->id;
    }
}
