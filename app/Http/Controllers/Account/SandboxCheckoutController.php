<?php

namespace App\Http\Controllers\Account;

use App\Actions\Billing\MarkPaymentFailed;
use App\Actions\Billing\MarkPaymentPaid;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Stand-in for the provider's hosted payment page. Exists only while the sandbox driver is active and never in production. */
class SandboxCheckoutController extends Controller
{
    public function show(Request $request, Payment $payment): View
    {
        $this->guard($request, $payment);

        return view('account.checkout.sandbox', ['payment' => $payment->load('subscription.package')]);
    }

    public function complete(Request $request, Payment $payment, MarkPaymentPaid $paid, MarkPaymentFailed $failed): RedirectResponse
    {
        $this->guard($request, $payment);

        $outcome = $request->validate(['outcome' => ['required', 'in:paid,failed']])['outcome'];

        if ($outcome === 'paid') {
            $paid->handle($payment);

            return redirect()->route('account.subscription.show')->with('status', __('تمت عملية الدفع بنجاح.'));
        }

        $failed->handle($payment, 'declined');

        return redirect()->route('account.subscription.show')->with('error', __('لم تكتمل عملية الدفع. يمكنك المحاولة مرة أخرى.'));
    }

    private function guard(Request $request, Payment $payment): void
    {
        abort_unless(config('itizan.payments.driver') === 'sandbox' && ! app()->isProduction(), 404);
        abort_unless($payment->user_id === $request->user()->id, 403);
        abort_unless($payment->status === PaymentStatus::Pending, 404);
    }
}
