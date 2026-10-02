<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderUpdatedNotification;
use Illuminate\Support\Facades\DB;

/** Fulfilment state machine: approved → preparing → out for delivery → delivered. */
class AdvanceOrder
{
    /** current status => [next status => roles allowed to make the move] */
    private const TRANSITIONS = [
        'approved' => ['preparing' => [Role::Pharmacy, Role::Admin]],
        'preparing' => ['out_for_delivery' => [Role::Pharmacy, Role::Delivery, Role::Admin]],
        'out_for_delivery' => ['delivered' => [Role::Delivery, Role::Pharmacy, Role::Admin]],
    ];

    private const NOTES = [
        'preparing' => 'بدأت الصيدلية بتجهيز طلبك',
        'out_for_delivery' => 'طلبك في الطريق إليك',
        'delivered' => 'تم تسليم طلبك',
    ];

    public function handle(Order $order, User $actor, OrderStatus $to): Order
    {
        $order = DB::transaction(function () use ($order, $actor, $to) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $allowedRoles = self::TRANSITIONS[$locked->status->value][$to->value] ?? null;

            if ($allowedRoles === null) {
                throw new BusinessRuleViolation(__('لا يمكن نقل الطلب إلى هذه الحالة.'));
            }

            if (! in_array($actor->role, $allowedRoles, true)) {
                throw new BusinessRuleViolation(__('ليست لديك صلاحية لهذا التحديث.'));
            }

            // A pharmacy can only move its own orders.
            if ($actor->role === Role::Pharmacy && $locked->pharmacy_id !== $actor->pharmacy_id) {
                throw new BusinessRuleViolation(__('هذا الطلب مُسند إلى صيدلية أخرى.'));
            }

            $locked->update([
                'status' => $to,
                'delivered_at' => $to === OrderStatus::Delivered ? now() : null,
            ]);
            $locked->recordEvent($to, self::NOTES[$to->value], $actor);

            return $locked;
        });

        $order->user->notify(new OrderUpdatedNotification($order));

        return $order;
    }
}
