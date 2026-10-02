<?php

namespace App\Policies;

use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $appointment->user_id === $user->id
            || $this->isAssignedPractitioner($user, $appointment)
            || $user->hasRole(Role::Admin, Role::Support, Role::CareTeam)
            // Any doctor can see the instant-consultation queue.
            || ($user->hasRole(Role::Doctor) && $appointment->practitioner_id === null && $appointment->is_instant);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $appointment->user_id === $user->id
            || $this->isAssignedPractitioner($user, $appointment)
            || $user->hasRole(Role::Admin, Role::Support);
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $this->isAssignedPractitioner($user, $appointment)
            && $appointment->status === AppointmentStatus::Confirmed;
    }

    public function claim(User $user, Appointment $appointment): bool
    {
        return $user->hasRole(Role::Doctor)
            && $appointment->practitioner_id === null
            && $appointment->is_instant
            && $appointment->status === AppointmentStatus::Requested;
    }

    private function isAssignedPractitioner(User $user, Appointment $appointment): bool
    {
        return $appointment->practitioner_id !== null
            && $user->practitioner?->id === $appointment->practitioner_id;
    }
}
