<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderUpdatedNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The licensed pharmacy confirms availability, final price and expected delivery time.
 * The client must approve this quote before anything is prepared.
 */
class QuoteOrder
{
    /**
     * @param  array<int, array{available: bool, unit_price_halalas?: int}>  $lines  keyed by order item id
     */
    public function handle(Order $order, User $pharmacist, array $lines, CarbonInterface $eta, ?int $deliveryFeeHalalas = null): Order
    {
        if ($pharmacist->role !== Role::Pharmacy || $pharmacist->pharmacy_id === null || ! $pharmacist->pharmacy?->is_active) {
            throw new BusinessRuleViolation(__('هذا الإجراء متاح لموظفي الصيدليات المرخّصة فقط.'));
        }

        $order = DB::transaction(function () use ($order, $pharmacist, $lines, $eta, $deliveryFeeHalalas) {
            $locked = Order::query()->lockForUpdate()->with('items')->findOrFail($order->id);

            if (! in_array($locked->status, [OrderStatus::Requested, OrderStatus::Quoted], true)) {
                throw new BusinessRuleViolation(__('لا يمكن تسعير هذا الطلب في حالته الحالية.'));
            }

            if ($locked->pharmacy_id !== null && $locked->pharmacy_id !== $pharmacist->pharmacy_id) {
                throw new BusinessRuleViolation(__('هذا الطلب مُسند إلى صيدلية أخرى.'));
            }

            foreach ($locked->items as $item) {
                $line = $lines[$item->id] ?? ['available' => false];
                $item->update([
                    'is_available' => (bool) $line['available'],
                    'unit_price_halalas' => $line['available'] ? ($line['unit_price_halalas'] ?? $item->unit_price_halalas) : $item->unit_price_halalas,
                ]);
            }

            $locked->load('items');
            $subtotal = $locked->items->sum(fn ($item) => $item->lineTotalHalalas());

            if ($subtotal === 0) {
                $locked->update([
                    'pharmacy_id' => $pharmacist->pharmacy_id,
                    'status' => OrderStatus::Rejected,
                    'subtotal_halalas' => 0,
                    'total_halalas' => 0,
                ]);
                $locked->recordEvent(OrderStatus::Rejected, 'المنتجات المطلوبة غير متوفرة حاليًا', $pharmacist);

                return $locked;
            }

            $fee = $locked->free_delivery ? 0 : ($deliveryFeeHalalas ?? (int) config('itizan.delivery.fee_halalas'));

            $locked->update([
                'pharmacy_id' => $pharmacist->pharmacy_id,
                'status' => OrderStatus::Quoted,
                'subtotal_halalas' => $subtotal,
                'delivery_fee_halalas' => $fee,
                'total_halalas' => $subtotal + $fee,
                'eta_at' => $eta,
                'quoted_at' => now(),
            ]);
            $locked->recordEvent(OrderStatus::Quoted, 'تم تأكيد التوفر والسعر وموعد التوصيل المتوقع', $pharmacist);

            return $locked;
        });

        $order->user->notify(new OrderUpdatedNotification($order));

        return $order;
    }
}
