<?php

namespace App\Actions\Billing;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhook
{
    public function __construct(
        private readonly MarkPaymentPaid $markPaid,
        private readonly MarkPaymentFailed $markFailed,
    ) {}

    /**
     * @param  array{reference?: string, status?: string, reason?: string}  $payload  already signature-verified
     */
    public function handle(array $payload): ?Payment
    {
        $payment = Payment::query()->where('provider_reference', $payload['reference'] ?? '')->first();

        if ($payment === null) {
            Log::warning('payment.webhook_unknown_reference', ['reference' => $payload['reference'] ?? null]);

            return null;
        }

        return match ($payload['status'] ?? null) {
            'paid' => $this->markPaid->handle($payment),
            'failed' => $this->markFailed->handle($payment, $payload['reason'] ?? null),
            default => $payment,
        };
    }
}
