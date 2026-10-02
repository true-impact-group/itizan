<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->id || $user->hasRole(Role::Support, Role::Admin);
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(Role::Support, Role::Admin);
    }
}
