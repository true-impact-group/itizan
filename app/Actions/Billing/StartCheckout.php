<?php

namespace App\Actions\Billing;

use App\Actions\Consent\RecordConsent;
use App\Enums\ConsentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StartCheckout
{
    /** A client can buy the next package while the current one has at most this many days left. */
    public const RENEWAL_WINDOW_DAYS = 30;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordConsent $consents,
    ) {}

    public function handle(User $client, Package $package, PaymentMethod $method, bool $autoRenew, ?string $email = null): Payment
    {
        if (blank($client->email) && blank($email)) {
            throw new BusinessRuleViolation(__('أضف بريدك الإلكتروني لإرسال الإيصال وتنبيهات المتابعة.'));
        }

        if (! $package->is_active) {
            throw new BusinessRuleViolation(__('هذه الباقة غير متاحة حاليًا.'));
        }

        if (! $method->isEnabled()) {
            throw new BusinessRuleViolation(__('وسيلة الدفع هذه غير متاحة حاليًا.'));
        }

        if ($autoRenew && $method->isInstallment()) {
            throw new BusinessRuleViolation(__('الدفع عبر :method لا يدعم التجديد التلقائي، وسيكون التجديد بطلب جديد منك.', ['method' => $method->label()]));
        }

        $payment = DB::transaction(function () use ($client, $package, $method, $autoRenew, $email) {
            if (blank($client->email)) {
                $client->update(['email' => mb_strtolower($email)]);
            }

            // Abandoned checkouts are closed so only one pending purchase exists at a time.
            Subscription::query()->where('user_id', $client->id)->where('status', SubscriptionStatus::Pending->value)
                ->update(['status' => SubscriptionStatus::Cancelled->value, 'cancelled_at' => now(), 'cancel_reason' => 'abandoned']);

            $current = Subscription::query()->where('user_id', $client->id)->current()->orderByDesc('ends_at')->first();

            if ($current && $current->ends_at->gt(now()->addDays(self::RENEWAL_WINDOW_DAYS))) {
                throw new BusinessRuleViolation(__('لديك اشتراك فعّال حتى :date. يمكنك التجديد قبل انتهائه بـ :days يومًا.', [
                    'date' => $current->ends_at->translatedFormat('j F Y'),
                    'days' => self::RENEWAL_WINDOW_DAYS,
                ]));
            }

            $starts = $current?->ends_at ?? now();

            $subscription = Subscription::create([
                'user_id' => $client->id,
                'package_id' => $package->id,
                'status' => SubscriptionStatus::Pending,
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->addMonths($package->duration_months),
                'auto_renew' => $autoRenew,
                'auto_renew_consented_at' => $autoRenew ? now() : null,
                'payment_method' => $method,
                'price_halalas' => $package->price_halalas,
                'followups_included' => $package->included_followups,
            ]);

            if ($autoRenew) {
                $this->consents->handle($client, ConsentType::AutoRenewal);
            }

            return Payment::create([
                'user_id' => $client->id,
                'subscription_id' => $subscription->id,
                'amount_halalas' => $package->chargeHalalas(),
                'vat_halalas' => $package->vatHalalas(),
                'currency' => config('itizan.currency'),
                'method' => $method,
                'status' => PaymentStatus::Pending,
                'provider' => $this->gateway->name(),
            ]);
        });

        // The external call happens outside the DB transaction.
        try {
            $charge = $this->gateway->createCharge($payment);
        } catch (Throwable $e) {
            Log::error('payment.charge_failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            app(MarkPaymentFailed::class)->handle($payment, 'gateway_error');

            throw new BusinessRuleViolation(__('تعذر بدء عملية الدفع الآن. حاول مرة أخرى بعد قليل.'));
        }

        $payment->update([
            'provider_reference' => $charge->reference,
            'meta' => ['checkout_url' => $charge->checkoutUrl],
        ]);

        return $payment;
    }
}
