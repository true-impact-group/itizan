<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Billing\RefundPayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = PaymentStatus::tryFrom((string) $request->query('status'));

        return view('admin.payments.index', [
            'status' => $status,
            'payments' => Payment::query()->with(['user', 'subscription.package'])
                ->when($status, fn ($query) => $query->where('status', $status->value))
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function refund(Payment $payment, RefundPayment $refund): RedirectResponse
    {
        $this->authorize('refund', $payment);

        $refund->handle($payment);

        return back()->with('status', __('تم استرداد المبلغ وإلغاء الاشتراك.'));
    }
}
