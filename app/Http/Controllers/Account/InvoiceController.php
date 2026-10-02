<?php

namespace App\Http\Controllers\Account;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;

class InvoiceController extends Controller
{
    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);
        abort_unless(in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded], true), 404);

        return view('account.invoices.show', ['payment' => $payment->load(['user', 'subscription.package'])]);
    }
}
