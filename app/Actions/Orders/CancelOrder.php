<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderUpdatedNotification;
use Illuminate\Support\Facades\DB;

class CancelOrder
{
    public function handle(Order $order, User $by, ?string $reason = null): Order
    {
        $order = DB::transaction(function () use ($order, $by, $reason) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $isPharmacy = $by->role === Role::Pharmacy;

            if ($isPharmacy) {
                $allowed = in_array($locked->status, [OrderStatus::Requested, OrderStatus::Quoted], true)
                    && ($locked->pharmacy_id === null || $locked->pharmacy_id === $by->pharmacy_id);
                $target = OrderStatus::Rejected;
            } else {
                $allowed = $locked->canBeCancelledByClient() || ($by->role === Role::Admin && ! $locked->status->isFinal());
                $target = OrderStatus::Cancelled;
            }

            if (! $allowed) {
                throw new BusinessRuleViolation(__('لا يمكن إلغاء الطلب في حالته الحالية.'));
            }

            $locked->update([
                'status' => $target,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
            $locked->recordEvent($target, $target === OrderStatus::Rejected ? 'تعذّر على الصيدلية تنفيذ الطلب' : 'تم إلغاء الطلب', $by);

            return $locked;
        });

        if ($by->id !== $order->user_id) {
            $order->user->notify(new OrderUpdatedNotification($order));
        }

        return $order;
    }
}
