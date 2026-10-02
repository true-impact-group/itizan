<?php

namespace App\Actions\Billing;

use App\Actions\Consent\RecordConsent;
use App\Enums\ConsentType;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Subscription;
use App\Models\User;

/** Self-service subscription changes. None of them touch the paid period, only what happens next. */
class ManageSubscription
{
    public function __construct(private readonly RecordConsent $consents) {}

    public function disableAutoRenew(Subscription $subscription, User $client): Subscription
    {
        $this->assertManageable($subscription);

        $subscription->update(['auto_renew' => false, 'auto_renew_consented_at' => null]);
        $this->consents->handle($client, ConsentType::AutoRenewal, false);

        return $subscription;
    }

    /** Re-enabling needs a fresh, separate consent. */
    public function enableAutoRenew(Subscription $subscription, User $client): Subscription
    {
        $this->assertManageable($subscription);

        if ($subscription->payment_method?->isInstallment()) {
            throw new BusinessRuleViolation(__('الدفع بالتقسيط لا يدعم التجديد التلقائي.'));
        }

        $subscription->update(['auto_renew' => true, 'auto_renew_consented_at' => now()]);
        $this->consents->handle($client, ConsentType::AutoRenewal);

        return $subscription;
    }

    public function changePaymentMethod(Subscription $subscription, PaymentMethod $method): Subscription
    {
        $this->assertManageable($subscription);

        if (! $method->isEnabled()) {
            throw new BusinessRuleViolation(__('وسيلة الدفع هذه غير متاحة حاليًا.'));
        }

        if ($method->isInstallment() && $subscription->auto_renew) {
            throw new BusinessRuleViolation(__('ألغِ التجديد التلقائي أولًا، لأن الدفع بالتقسيط لا يدعمه.'));
        }

        $subscription->update(['payment_method' => $method]);

        return $subscription;
    }

    private function assertManageable(Subscription $subscription): void
    {
        if ($subscription->status !== SubscriptionStatus::Active) {
            throw new BusinessRuleViolation(__('لا يمكن تعديل هذا الاشتراك.'));
        }
    }
}
