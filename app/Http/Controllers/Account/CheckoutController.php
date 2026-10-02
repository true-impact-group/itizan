<?php

namespace App\Http\Controllers\Account;

use App\Actions\Billing\StartCheckout;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CheckoutRequest;
use App\Models\Package;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CheckoutController extends Controller
{
    public function show(Package $package): View
    {
        abort_unless($package->is_active, 404);

        return view('account.checkout.show', [
            'package' => $package,
            'methods' => PaymentMethod::enabled(),
            'policies' => Page::query()->published()
                ->whereIn('slug', ['terms', 'subscription-renewal-cancellation-refund', 'privacy'])->orderBy('sort_order')->get(),
        ]);
    }

    public function store(CheckoutRequest $request, Package $package, StartCheckout $checkout): RedirectResponse
    {
        $payment = $checkout->handle(
            $request->user(),
            $package,
            PaymentMethod::from($request->string('payment_method')->toString()),
            $request->boolean('auto_renew'),
            $request->input('email'),
        );

        return redirect()->away($payment->meta['checkout_url']);
    }
}
