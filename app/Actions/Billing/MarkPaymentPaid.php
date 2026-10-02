<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\DB;

class MarkPaymentPaid
{
    /** Idempotent: calling it again for an already paid payment changes nothing (webhooks are delivered more than once). */
    public function handle(Payment $payment): Payment
    {
        $activated = false;

        $payment = DB::transaction(function () use ($payment, &$activated) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status === PaymentStatus::Paid || $locked->status === PaymentStatus::Refunded) {
                return $locked;
            }

            $locked->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
                'invoice_number' => sprintf('ITZ-%s-%06d', now()->format('Y'), $locked->id),
            ]);

            $subscription = Subscription::query()->lockForUpdate()->with('package')->findOrFail($locked->subscription_id);

            // A late "paid" confirmation is authoritative: if the provider first reported a failure (or the checkout
            // was closed as abandoned) and the client's money was then taken, the subscription must still start.
            $reactivatable = $subscription->status === SubscriptionStatus::Cancelled
                && in_array($subscription->cancel_reason, ['payment_failed', 'abandoned'], true);

            if ($subscription->status === SubscriptionStatus::Pending || $reactivatable) {
                // If payment arrived after the planned start, the period starts now.
                $starts = $subscription->starts_at->lt(now()) ? now() : $subscription->starts_at;
                $months = $subscription->package->duration_months;

                $subscription->update([
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => $starts,
                    'ends_at' => $starts->copy()->addMonths($months),
                    'cancelled_at' => null,
                    'cancel_reason' => null,
                ]);
                $activated = true;
            }

            return $locked;
        });

        if ($activated) {
            $payment->user->notify(new GenericNotification(
                'تم تفعيل اشتراكك',
                'شكرًا لثقتك. اشتراكك فعّال الآن، ويمكنك متابعة الخطوة التالية من حسابك.',
                route('account.subscription.show'),
            ));
        }

        return $payment;
    }
}
