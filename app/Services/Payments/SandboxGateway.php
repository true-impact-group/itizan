<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Str;

/** Local/testing gateway: no money moves. Checkout is an internal page where the outcome is simulated. */
class SandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function createCharge(Payment $payment): ChargeResult
    {
        return new ChargeResult(
            reference: 'sbx_'.Str::lower(Str::random(24)),
            checkoutUrl: route('checkout.sandbox.show', $payment),
        );
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('itizan.payments.webhook_secret');

        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    public function refund(Payment $payment): bool
    {
        return true;
    }
}
