<?php

namespace App\Actions\Notify;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\Notification;

/** Sends a generic "needs attention" alert to every active member of a staff role. */
class NotifyStaff
{
    public function handle(Role $role, string $title, string $body, ?string $url = null): void
    {
        $recipients = User::query()->where('role', $role->value)->where('is_active', true)->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new GenericNotification($title, $body, $url));
        }
    }
}
