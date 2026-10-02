<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Contract for a payment provider (Apple Pay / mada / Tabby / Tamara are provided by the PSP).
 * Only the sandbox driver ships; a real provider implements this interface and is selected with PAYMENT_DRIVER.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Creates the charge at the provider and returns where the client should complete payment. */
    public function createCharge(Payment $payment): ChargeResult;

    /** Verifies that a webhook really came from the provider. */
    public function verifyWebhookSignature(string $payload, ?string $signature): bool;

    public function refund(Payment $payment): bool;
}
