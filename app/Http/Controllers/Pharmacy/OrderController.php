<?php

namespace App\Http\Controllers\Pharmacy;

use App\Actions\Orders\AdvanceOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\QuoteOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A pharmacy sees its own orders and the unassigned new requests it can pick up. */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $pharmacyId = $request->user()->pharmacy_id;
        $tab = in_array($request->query('tab'), ['new', 'active', 'done'], true) ? $request->query('tab') : 'new';

        $query = Order::query()->with(['items', 'user'])
            ->where(fn ($q) => $q->where('pharmacy_id', $pharmacyId)
                ->orWhere(fn ($unassigned) => $unassigned->whereNull('pharmacy_id')->where('status', OrderStatus::Requested->value)));

        $query = match ($tab) {
            'new' => $query->whereIn('status', [OrderStatus::Requested->value, OrderStatus::Quoted->value])->oldest(),
            'active' => $query->whereIn('status', [OrderStatus::Approved->value, OrderStatus::Preparing->value, OrderStatus::OutForDelivery->value])->oldest(),
            default => $query->whereIn('status', [OrderStatus::Delivered->value, OrderStatus::Rejected->value, OrderStatus::Cancelled->value])->latest(),
        };

        return view('pharmacy.orders.index', ['orders' => $query->paginate(15)->withQueryString(), 'tab' => $tab]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['items', 'events', 'user', 'prescription']);
        Audit::log('order.viewed', $order);

        return view('pharmacy.orders.show', ['order' => $order]);
    }

    public function quote(Request $request, Order $order, QuoteOrder $quote): RedirectResponse
    {
        $this->authorize('quote', $order);

        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.available' => ['nullable', 'boolean'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'eta_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        $lines = [];
        foreach ($data['lines'] as $itemId => $line) {
            $lines[(int) $itemId] = [
                'available' => (bool) ($line['available'] ?? false),
                'unit_price_halalas' => isset($line['unit_price']) ? (int) round($line['unit_price'] * 100) : null,
            ];
        }

        $quote->handle(
            $order,
            $request->user(),
            array_map(fn ($line) => array_filter($line, fn ($v) => $v !== null), $lines),
            Carbon::createFromFormat('Y-m-d\TH:i', $data['eta_at']),
            isset($data['delivery_fee']) ? (int) round($data['delivery_fee'] * 100) : null,
        );

        return redirect()->route('pharmacy.orders.show', $order)->with('status', __('تم إرسال عرض السعر للعميل.'));
    }

    public function reject(Request $request, Order $order, CancelOrder $cancel): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $cancel->handle($order, $request->user(), $data['reason']);

        return redirect()->route('pharmacy.orders.index')->with('status', __('تم رفض الطلب.'));
    }

    public function advance(Request $request, Order $order, AdvanceOrder $advance): RedirectResponse
    {
        $this->authorize('advance', $order);

        $data = $request->validate(['to' => ['required', Rule::in([OrderStatus::Preparing->value, OrderStatus::OutForDelivery->value, OrderStatus::Delivered->value])]]);

        $advance->handle($order, $request->user(), OrderStatus::from($data['to']));

        return back()->with('status', __('تم تحديث حالة الطلب.'));
    }
}
