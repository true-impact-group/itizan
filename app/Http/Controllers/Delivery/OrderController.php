<?php

namespace App\Http\Controllers\Delivery;

use App\Actions\Orders\AdvanceOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The delivery partner only needs to know where to deliver: no medication or clinical details are shown. */
class OrderController extends Controller
{
    public function index(): View
    {
        return view('delivery.orders.index', [
            'orders' => Order::query()->with(['user', 'pharmacy'])
                ->whereIn('status', [OrderStatus::Preparing->value, OrderStatus::OutForDelivery->value])
                ->oldest()->paginate(20),
        ]);
    }

    public function advance(Request $request, Order $order, AdvanceOrder $advance): RedirectResponse
    {
        $this->authorize('advance', $order);

        $data = $request->validate(['to' => ['required', Rule::in([OrderStatus::OutForDelivery->value, OrderStatus::Delivered->value])]]);

        $advance->handle($order, $request->user(), OrderStatus::from($data['to']));

        return back()->with('status', __('تم تحديث حالة التوصيل.'));
    }
}
