<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/** Full refund of a paid subscription; the subscription is cancelled with it. */
class RefundPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function handle(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== PaymentStatus::Paid) {
                throw new BusinessRuleViolation(__('يمكن استرداد المدفوعات المكتملة فقط.'));
            }

            if (! $this->gateway->refund($locked)) {
                throw new BusinessRuleViolation(__('رفض مزود الدفع عملية الاسترداد.'));
            }

            $locked->update([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => now(),
                'refunded_halalas' => $locked->amount_halalas,
            ]);

            $locked->subscription()->update([
                'status' => SubscriptionStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancel_reason' => 'refunded',
                'auto_renew' => false,
            ]);

            Audit::log('payment.refunded', $locked, ['amount_halalas' => $locked->amount_halalas]);

            return $locked;
        });
    }
}
