<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Billing\ProcessPaymentWebhook;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server callback from the payment provider. Authenticated by an HMAC signature, not a session.
 * Processing is idempotent, so provider retries are safe.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, ProcessPaymentWebhook $process): JsonResponse
    {
        $payload = $request->getContent();

        if (! $gateway->verifyWebhookSignature($payload, $request->header('X-Signature'))) {
            Log::warning('payment.webhook_bad_signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $data = json_decode($payload, true);

        if (! is_array($data)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        $process->handle($data);

        return response()->json(['received' => true]);
    }
}
