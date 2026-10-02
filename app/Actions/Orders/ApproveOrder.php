<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** The client agrees to the exact price and order the pharmacy quoted. */
class ApproveOrder
{
    public function handle(Order $order, User $client, int $acknowledgedTotalHalalas): Order
    {
        return DB::transaction(function () use ($order, $client, $acknowledgedTotalHalalas) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->user_id !== $client->id || $locked->status !== OrderStatus::Quoted) {
                throw new BusinessRuleViolation(__('لا يوجد عرض سعر بانتظار موافقتك.'));
            }

            // Guards against the pharmacy re-quoting while the client had the old price on screen.
            if ($acknowledgedTotalHalalas !== $locked->total_halalas) {
                throw new BusinessRuleViolation(__('تغيّر السعر. راجع العرض المحدّث قبل الموافقة.'));
            }

            $locked->update(['status' => OrderStatus::Approved, 'approved_at' => now()]);
            $locked->recordEvent(OrderStatus::Approved, 'وافق العميل على السعر وأُرسل الطلب للتجهيز', $client);

            return $locked;
        });
    }
}
