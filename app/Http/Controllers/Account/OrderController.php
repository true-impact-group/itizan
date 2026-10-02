<?php

namespace App\Http\Controllers\Account;

use App\Actions\Orders\ApproveOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\RequestOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreOrderRequest;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.orders.index', [
            'orders' => Order::query()->with('items')->where('user_id', $request->user()->id)->latest()->paginate(10),
        ]);
    }

    public function create(Request $request): View
    {
        return view('account.orders.create', [
            'hasSubscription' => $request->user()->hasActiveSubscription(),
            'products' => Product::query()->orderable()->orderBy('type')->orderBy('name_ar')->get(),
            'prescriptions' => Prescription::query()->active()->where('user_id', $request->user()->id)->get(),
            'selectedPrescription' => $request->integer('prescription') ?: null,
            'cities' => config('itizan.cities'),
            'freeCities' => config('itizan.delivery.free_cities'),
        ]);
    }

    public function store(StoreOrderRequest $request, RequestOrder $order): RedirectResponse
    {
        $created = $order->handle($request->user(), $request->validated());

        return redirect()->route('account.orders.show', $created)
            ->with('status', __('تم إرسال طلبك إلى الصيدلية. ستراجع التوفر والسعر ثم تطلب موافقتك.'));
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);
        abort_unless($order->user_id === request()->user()->id, 403);

        $order->load(['items', 'events', 'pharmacy']);

        return view('account.orders.show', ['order' => $order]);
    }

    public function approve(Request $request, Order $order, ApproveOrder $approve): RedirectResponse
    {
        $this->authorize('approve', $order);

        $request->validate([
            'total_halalas' => ['required', 'integer'],
            'agree' => ['accepted'],
        ]);

        $approve->handle($order, $request->user(), $request->integer('total_halalas'));

        return redirect()->route('account.orders.show', $order)->with('status', __('شكرًا، تمت الموافقة على الطلب وسيبدأ تجهيزه.'));
    }

    public function cancel(Request $request, Order $order, CancelOrder $cancel): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $cancel->handle($order, $request->user(), $request->input('reason'));

        return redirect()->route('account.orders.show', $order)->with('status', __('تم إلغاء الطلب.'));
    }
}
