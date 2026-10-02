<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;

/**
 * Only doctors create or stop prescriptions. A client can view and log doses but never edit a prescription.
 */
class PrescriptionPolicy
{
    public function view(User $user, Prescription $prescription): bool
    {
        if ($prescription->user_id === $user->id || $user->hasRole(Role::Doctor, Role::CareTeam)) {
            return true;
        }

        // Pharmacy staff see a prescription only while an order for their pharmacy (or an unassigned one) uses it.
        return $user->hasRole(Role::Pharmacy)
            && Order::query()
                ->where('prescription_id', $prescription->id)
                ->where(fn ($query) => $query
                    ->where('pharmacy_id', $user->pharmacy_id)
                    ->orWhere(fn ($q) => $q->whereNull('pharmacy_id')->where('status', OrderStatus::Requested->value)))
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Doctor);
    }

    /** Stopping or changing is limited to the doctor who issued it. */
    public function update(User $user, Prescription $prescription): bool
    {
        return $user->hasRole(Role::Doctor)
            && $user->practitioner?->id === $prescription->practitioner_id;
    }

    public function logDose(User $user, Prescription $prescription): bool
    {
        return $prescription->user_id === $user->id && $prescription->isActive();
    }
}
