<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class MarkPaymentFailed
{
    public function handle(Payment $payment, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $reason) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            // A payment that already succeeded is never downgraded by a late failure message.
            if ($locked->status !== PaymentStatus::Pending) {
                return $locked;
            }

            $locked->update([
                'status' => PaymentStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => $reason,
            ]);

            Subscription::query()->whereKey($locked->subscription_id)
                ->where('status', SubscriptionStatus::Pending->value)
                ->update(['status' => SubscriptionStatus::Cancelled->value, 'cancelled_at' => now(), 'cancel_reason' => 'payment_failed']);

            return $locked;
        });
    }
}
