<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\MarkPaymentFailed;
use App\Actions\Billing\MarkPaymentPaid;
use App\Enums\ConsentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\Payments\ChargeResult;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SandboxGateway;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    private User $client;

    private Package $sixMonths;

    private Package $annual;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-05 12:00:00'));
        $this->seedCatalog();
        $this->client = $this->client();
        $this->sixMonths = Package::where('slug', 'six-months')->firstOrFail();
        $this->annual = Package::where('slug', 'annual')->firstOrFail();
    }

    private function checkout(Package $package, array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->client)->post(route('account.checkout.store', $package), [
            'payment_method' => 'mada',
            'accept_terms' => '1',
            ...$data,
        ]);
    }

    private function pay(Payment $payment, string $outcome = 'paid', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->client)->post(route('checkout.sandbox.complete', $payment), ['outcome' => $outcome]);
    }

    // ---- the page ------------------------------------------------------------------------------

    public function test_the_checkout_page_shows_the_total_the_vat_and_what_will_actually_be_charged(): void
    {
        $this->actingAs($this->client)->get(route('account.checkout.show', $this->sixMonths))
            ->assertOk()
            ->assertSee('868.70 ريال')                       // value without VAT
            ->assertSee('130.30 ريال')                       // VAT contained in 999
            ->assertSee('الإجمالي المخصوم فعليًا')
            ->assertSee('999 ريال')
            ->assertSee('السعر شامل ضريبة القيمة المضافة.')
            ->assertSee('الدواء والتحاليل غير مشمولين ما لم يُذكر خلاف ذلك.')
            ->assertSee('بمتوسط 166.50 ريال شهريًا')
            ->assertSee('تشمل الباقة 3 استشارات متابعة.');
    }

    public function test_the_checkout_page_links_the_renewal_cancellation_and_refund_policy(): void
    {
        $this->actingAs($this->client)->get(route('account.checkout.show', $this->annual))
            ->assertSee(route('policies.show', 'subscription-renewal-cancellation-refund'), false)
            ->assertSee(route('policies.show', 'terms'), false);
    }

    public function test_automatic_renewal_is_a_separate_unticked_optional_consent(): void
    {
        $html = $this->actingAs($this->client)->get(route('account.checkout.show', $this->sixMonths))->getContent();

        $this->assertStringContainsString('name="auto_renew"', $html);
        $this->assertStringContainsString('name="accept_terms"', $html);
        $this->assertDoesNotMatchRegularExpression('/name="auto_renew"[^>]*checked/', $html);
        $this->assertStringContainsString('موافقة مستقلة', $html);
    }

    public function test_only_enabled_payment_methods_are_offered_and_instalments_appear_when_enabled(): void
    {
        $response = $this->actingAs($this->client)->get(route('account.checkout.show', $this->sixMonths));
        $response->assertSee('Apple Pay')->assertSee('مدى')->assertDontSee('تابي')->assertDontSee('تمارا');

        config(['itizan.payments.methods.tabby' => true]);
        $this->actingAs($this->client)->get(route('account.checkout.show', $this->sixMonths))
            ->assertSee('تابي')->assertSee('249.75 ريال')->assertSee('لا يدعم التجديد التلقائي');
    }

    public function test_an_inactive_package_cannot_be_bought(): void
    {
        $this->sixMonths->update(['is_active' => false]);

        $this->actingAs($this->client)->get(route('account.checkout.show', $this->sixMonths))->assertNotFound();
        $this->checkout($this->sixMonths)->assertSessionHas('error', 'هذه الباقة غير متاحة حاليًا.');
    }

    public function test_guests_and_staff_cannot_reach_checkout(): void
    {
        $this->get(route('account.checkout.show', $this->sixMonths))->assertRedirect(route('login'));
        $this->actingAs($this->admin())->get(route('account.checkout.show', $this->sixMonths))->assertForbidden();
    }

    // ---- starting a purchase ------------------------------------------------------------------------------

    public function test_starting_checkout_creates_a_pending_subscription_and_payment_then_goes_to_the_provider(): void
    {
        $response = $this->checkout($this->sixMonths);

        $payment = Payment::firstOrFail();
        $subscription = Subscription::firstOrFail();

        $response->assertRedirect(route('checkout.sandbox.show', $payment));
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(PaymentMethod::Mada, $payment->method);
        $this->assertSame(99900, $payment->amount_halalas);
        $this->assertSame(13030, $payment->vat_halalas);
        $this->assertSame('SAR', $payment->currency);
        $this->assertSame('sandbox', $payment->provider);
        $this->assertStringStartsWith('sbx_', $payment->provider_reference);
        $this->assertSame(SubscriptionStatus::Pending, $subscription->status);
        $this->assertSame($this->sixMonths->id, $subscription->package_id);
        $this->assertSame(3, $subscription->followups_included);
        $this->assertSame(99900, $subscription->price_halalas);
        $this->assertFalse($subscription->auto_renew);
    }

    public function test_checkout_input_is_validated(): void
    {
        $this->checkout($this->sixMonths, ['accept_terms' => '0'])->assertSessionHasErrors('accept_terms');
        $this->checkout($this->sixMonths, ['payment_method' => 'cash'])->assertSessionHasErrors('payment_method');
        $this->checkout($this->sixMonths, ['payment_method' => ''])->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_a_payment_method_that_is_not_enabled_is_refused(): void
    {
        $this->checkout($this->sixMonths, ['payment_method' => 'tabby'])->assertSessionHas('error', 'وسيلة الدفع هذه غير متاحة حاليًا.');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_vat_exclusive_packages_add_the_vat_on_top(): void
    {
        $this->sixMonths->update(['price_halalas' => 100000, 'vat_inclusive' => false]);

        $this->checkout($this->sixMonths);

        $payment = Payment::firstOrFail();
        $this->assertSame(115000, $payment->amount_halalas);
        $this->assertSame(15000, $payment->vat_halalas);
    }

    public function test_the_annual_package_lasts_twelve_months(): void
    {
        $this->checkout($this->annual);
        $this->pay(Payment::firstOrFail());

        $subscription = Subscription::firstOrFail();
        $this->assertTrue($subscription->ends_at->equalTo($subscription->starts_at->copy()->addMonths(12)));
        $this->assertSame(6, $subscription->followups_included);
    }

    // ---- renewal consent ------------------------------------------------------------------------------

    public function test_automatic_renewal_needs_its_own_consent_which_is_recorded_separately(): void
    {
        $this->checkout($this->sixMonths, ['auto_renew' => '1']);

        $subscription = Subscription::firstOrFail();
        $this->assertTrue($subscription->auto_renew);
        $this->assertNotNull($subscription->auto_renew_consented_at);
        $this->assertDatabaseHas('consent_records', ['user_id' => $this->client->id, 'type' => ConsentType::AutoRenewal->value, 'granted' => true]);
    }

    public function test_without_ticking_the_renewal_box_no_renewal_consent_exists_even_if_terms_are_accepted(): void
    {
        $this->checkout($this->sixMonths, ['auto_renew' => '0']);

        $this->assertFalse(Subscription::firstOrFail()->auto_renew);
        $this->assertDatabaseMissing('consent_records', ['type' => ConsentType::AutoRenewal->value]);
    }

    public function test_instalment_payments_cannot_be_combined_with_automatic_renewal(): void
    {
        config(['itizan.payments.methods.tabby' => true, 'itizan.payments.methods.tamara' => true]);

        foreach (['tabby', 'tamara'] as $method) {
            $this->checkout($this->sixMonths, ['payment_method' => $method, 'auto_renew' => '1'])->assertSessionHas('error');
        }
        $this->assertDatabaseCount('subscriptions', 0);

        $this->checkout($this->sixMonths, ['payment_method' => 'tabby'])->assertRedirect();
        $this->assertSame(PaymentMethod::Tabby, Payment::firstOrFail()->method);
    }

    // ---- paying ------------------------------------------------------------------------------

    public function test_a_successful_payment_activates_the_subscription_and_issues_a_numbered_receipt(): void
    {
        Notification::fake();
        $this->checkout($this->sixMonths);
        $payment = Payment::firstOrFail();

        $this->pay($payment)->assertRedirect(route('account.subscription.show'));

        $payment->refresh();
        $subscription = Subscription::firstOrFail();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(sprintf('ITZ-2026-%06d', $payment->id), $payment->invoice_number);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->starts_at->equalTo(now()));
        $this->assertTrue($subscription->ends_at->equalTo(now()->addMonths(6)));
        $this->assertTrue($subscription->isCurrent());
        Notification::assertSentTo($this->client, GenericNotification::class);
    }

    public function test_the_subscription_keeps_the_price_it_was_bought_at_when_the_package_price_changes_later(): void
    {
        $this->checkout($this->sixMonths);
        $this->pay(Payment::firstOrFail());

        $this->sixMonths->update(['price_halalas' => 150000, 'included_followups' => 9]);

        $subscription = Subscription::firstOrFail();
        $this->assertSame(99900, $subscription->price_halalas);
        $this->assertSame(3, $subscription->followups_included);
    }

    public function test_a_payment_can_only_be_completed_once(): void
    {
        $this->checkout($this->sixMonths);
        $payment = Payment::firstOrFail();
        $this->pay($payment);
        $invoice = $payment->fresh()->invoice_number;

        $this->pay($payment)->assertNotFound();

        $this->assertSame($invoice, $payment->fresh()->invoice_number);
    }

    public function test_a_failed_payment_leaves_no_active_subscription_and_the_client_can_retry(): void
    {
        $this->checkout($this->sixMonths);
        $payment = Payment::firstOrFail();

        $this->pay($payment, 'failed')->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->failed_at);
        $this->assertSame(SubscriptionStatus::Cancelled, Subscription::firstOrFail()->status);
        $this->assertNull(User::find($this->client->id)->currentSubscription);

        $this->checkout($this->sixMonths)->assertRedirect();
        $this->pay(Payment::latest('id')->firstOrFail());
        $this->assertSame(1, Subscription::where('status', 'active')->count());
    }

    public function test_the_outcome_must_be_paid_or_failed(): void
    {
        $this->checkout($this->sixMonths);

        $this->pay(Payment::firstOrFail(), 'refunded')->assertSessionHasErrors('outcome');
    }

    public function test_another_client_cannot_complete_my_payment(): void
    {
        $this->checkout($this->sixMonths);
        $payment = Payment::firstOrFail();

        $this->pay($payment, 'paid', $this->client())->assertForbidden();
        $this->actingAs($this->client())->get(route('checkout.sandbox.show', $payment))->assertForbidden();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_the_sandbox_payment_page_does_not_exist_in_production(): void
    {
        $this->checkout($this->sixMonths);
        $payment = Payment::firstOrFail();

        $this->app['env'] = 'production';

        $this->actingAs($this->client)->get(route('checkout.sandbox.show', $payment))->assertNotFound();

        // In "production" real CSRF checking is on, so send a valid token.
        $this->actingAs($this->client)->withSession(['_token' => 'tok'])
            ->post(route('checkout.sandbox.complete', $payment), ['_token' => 'tok', 'outcome' => 'paid'])
            ->assertNotFound();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_the_sandbox_gateway_is_refused_outright_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sandbox payment driver cannot be used in production');

        $this->app->make(PaymentGateway::class);
    }

    public function test_an_unknown_payment_driver_fails_loudly(): void
    {
        config(['itizan.payments.driver' => 'mystery']);

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(PaymentGateway::class);
    }

    public function test_the_sandbox_driver_is_used_by_default(): void
    {
        $this->assertInstanceOf(SandboxGateway::class, $this->app->make(PaymentGateway::class));
    }

    public function test_a_gateway_outage_is_reported_to_the_client_and_marks_the_attempt_failed(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new class extends SandboxGateway
        {
            public function createCharge(Payment $payment): ChargeResult
            {
                throw new \RuntimeException('provider timeout');
            }
        });

        $this->checkout($this->sixMonths)->assertSessionHas('error', 'تعذر بدء عملية الدفع الآن. حاول مرة أخرى بعد قليل.');

        $this->assertSame(PaymentStatus::Failed, Payment::firstOrFail()->status);
        $this->assertSame('gateway_error', Payment::firstOrFail()->failure_reason);
        $this->assertSame(SubscriptionStatus::Cancelled, Subscription::firstOrFail()->status);
    }

    // ---- buying while already subscribed ------------------------------------------------------------------------------

    public function test_a_client_with_a_long_running_subscription_cannot_buy_another_yet(): void
    {
        $this->subscribe($this->client, ['ends_at' => now()->addDays(90)], $this->sixMonths);

        $this->checkout($this->annual)->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_renewing_within_thirty_days_of_the_end_queues_the_new_period_after_the_current_one(): void
    {
        $current = $this->subscribe($this->client, ['ends_at' => now()->addDays(20)], $this->sixMonths);

        $this->checkout($this->annual)->assertRedirect();
        $this->pay(Payment::firstOrFail());

        $next = Subscription::where('id', '!=', $current->id)->firstOrFail();
        $this->assertTrue($next->starts_at->equalTo($current->ends_at), 'the renewal starts when the current one ends');
        $this->assertTrue($next->ends_at->equalTo($current->ends_at->copy()->addMonths(12)));
        $this->assertFalse($next->isCurrent(), 'it is not current yet');
        $this->assertTrue($current->fresh()->isCurrent());

        $this->actingAs($this->client)->get(route('account.subscription.show'))->assertSee('لديك اشتراك قادم يبدأ في');
    }

    public function test_an_abandoned_checkout_is_closed_when_a_new_one_starts(): void
    {
        $this->checkout($this->sixMonths);
        $abandoned = Subscription::firstOrFail();

        $this->checkout($this->annual);

        $this->assertSame(SubscriptionStatus::Cancelled, $abandoned->fresh()->status);
        $this->assertSame('abandoned', $abandoned->fresh()->cancel_reason);
        $this->assertSame(1, Subscription::where('status', 'pending')->count());
    }

    public function test_a_late_success_confirmation_still_activates_a_subscription_that_was_marked_failed(): void
    {
        $this->checkout($this->sixMonths);
        $payment = Payment::firstOrFail();
        app(MarkPaymentFailed::class)->handle($payment, 'declined');
        $this->assertSame(SubscriptionStatus::Cancelled, Subscription::firstOrFail()->status);

        app(MarkPaymentPaid::class)->handle($payment);

        $subscription = Subscription::firstOrFail();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->cancel_reason);
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_the_subscription_page_lists_receipts_and_the_receipt_page_is_printable_and_private(): void
    {
        $this->checkout($this->sixMonths);
        $payment = Payment::firstOrFail();
        $this->pay($payment);
        $payment->refresh();

        $this->actingAs($this->client)->get(route('account.subscription.show'))->assertSee($payment->invoice_number)->assertSee(route('account.invoices.show', $payment), false);

        $this->actingAs($this->client)->get(route('account.invoices.show', $payment))
            ->assertOk()
            ->assertSee('إيصال دفع')
            ->assertSee($payment->invoice_number)
            ->assertSee('130.30 ريال')
            ->assertSee('868.70 ريال')
            ->assertSee('data-print', false);

        $this->actingAs($this->client())->get(route('account.invoices.show', $payment))->assertForbidden();
    }

    public function test_an_unpaid_payment_has_no_receipt(): void
    {
        $this->checkout($this->sixMonths);

        $this->actingAs($this->client)->get(route('account.invoices.show', Payment::firstOrFail()))->assertNotFound();
    }
}
