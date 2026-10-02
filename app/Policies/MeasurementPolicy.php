<?php

namespace App\Policies;

use App\Models\Measurement;
use App\Models\User;

class MeasurementPolicy
{
    /** Clients may remove only their own self-reported entries, not measurements a clinician recorded. */
    public function delete(User $user, Measurement $measurement): bool
    {
        return $measurement->user_id === $user->id && $measurement->source === 'client';
    }
}
