<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id
            || $user->hasRole(Role::Admin, Role::Support)
            || $this->pharmacyCanSee($user, $order)
            || ($user->hasRole(Role::Delivery) && in_array($order->status, [OrderStatus::Approved, OrderStatus::Preparing, OrderStatus::OutForDelivery, OrderStatus::Delivered], true));
    }

    public function approve(User $user, Order $order): bool
    {
        return $order->user_id === $user->id && $order->status === OrderStatus::Quoted;
    }

    public function cancel(User $user, Order $order): bool
    {
        return ($order->user_id === $user->id && $order->canBeCancelledByClient())
            || ($user->hasRole(Role::Pharmacy) && $this->pharmacyCanSee($user, $order))
            || $user->hasRole(Role::Admin);
    }

    public function quote(User $user, Order $order): bool
    {
        return $this->pharmacyCanSee($user, $order)
            && in_array($order->status, [OrderStatus::Requested, OrderStatus::Quoted], true);
    }

    public function advance(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $user->hasRole(Role::Pharmacy, Role::Delivery, Role::Admin);
    }

    /** A pharmacy sees its own orders plus unassigned new requests it can pick up. */
    private function pharmacyCanSee(User $user, Order $order): bool
    {
        if (! $user->hasRole(Role::Pharmacy) || $user->pharmacy_id === null) {
            return false;
        }

        return $order->pharmacy_id === $user->pharmacy_id
            || ($order->pharmacy_id === null && $order->status === OrderStatus::Requested);
    }
}
