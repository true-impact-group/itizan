<?php

namespace Tests\Feature\Billing;

use App\Enums\ConsentType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\ConsentRecord;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
{
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->client = $this->client();
    }

    private function subscription(array $attributes = []): Subscription
    {
        return $this->subscribe($this->client, ['auto_renew' => true, 'auto_renew_consented_at' => now(), ...$attributes], Package::where('slug', 'six-months')->first());
    }

    public function test_the_page_shows_status_remaining_days_quota_and_payment_method(): void
    {
        $this->subscription(['followups_used' => 1, 'payment_method' => PaymentMethod::Mada]);

        $this->actingAs($this->client)->get(route('account.subscription.show'))
            ->assertOk()
            ->assertSee('الباقة النصف سنوية')
            ->assertSee('نشط')
            ->assertSee('1 / 3')
            ->assertSee('مدى')
            ->assertSee('التجديد التلقائي مفعّل');
    }

    public function test_without_a_subscription_the_page_offers_the_packages(): void
    {
        $this->actingAs($this->client)->get(route('account.subscription.show'))
            ->assertSee('ليس لديك اشتراك فعّال')
            ->assertSee('الباقة السنوية')
            ->assertSee('يوفر 499 ريال');
    }

    public function test_turning_off_automatic_renewal_keeps_the_subscription_running_and_records_the_withdrawal(): void
    {
        $subscription = $this->subscription();

        $this->actingAs($this->client)->delete(route('account.subscription.auto-renew.disable'))
            ->assertSessionHas('status', 'تم إلغاء التجديد التلقائي. يستمر اشتراكك حتى نهاية مدته.');

        $subscription->refresh();
        $this->assertFalse($subscription->auto_renew);
        $this->assertNull($subscription->auto_renew_consented_at);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->isCurrent());
        $this->assertDatabaseHas('consent_records', ['user_id' => $this->client->id, 'type' => ConsentType::AutoRenewal->value, 'granted' => false]);
    }

    public function test_turning_renewal_back_on_needs_a_fresh_explicit_consent(): void
    {
        $subscription = $this->subscription(['auto_renew' => false, 'auto_renew_consented_at' => null]);

        $this->actingAs($this->client)->post(route('account.subscription.auto-renew.enable'), ['auto_renew_consent' => '0'])->assertSessionHasErrors('auto_renew_consent');
        $this->assertFalse($subscription->fresh()->auto_renew);

        $this->actingAs($this->client)->post(route('account.subscription.auto-renew.enable'), ['auto_renew_consent' => '1'])->assertSessionHas('status');

        $this->assertTrue($subscription->fresh()->auto_renew);
        $this->assertNotNull($subscription->fresh()->auto_renew_consented_at);
        $this->assertDatabaseHas('consent_records', ['type' => ConsentType::AutoRenewal->value, 'granted' => true]);
    }

    public function test_renewal_cannot_be_enabled_on_an_instalment_subscription(): void
    {
        $subscription = $this->subscription(['auto_renew' => false, 'payment_method' => PaymentMethod::Tabby]);

        $this->actingAs($this->client)->post(route('account.subscription.auto-renew.enable'), ['auto_renew_consent' => '1'])
            ->assertSessionHas('error', 'الدفع بالتقسيط لا يدعم التجديد التلقائي.');

        $this->assertFalse($subscription->fresh()->auto_renew);
        $this->actingAs($this->client)->get(route('account.subscription.show'))->assertSee('لا يدعم التجديد التلقائي');
    }

    public function test_the_payment_method_for_renewal_can_be_updated_to_an_enabled_method(): void
    {
        $subscription = $this->subscription(['payment_method' => PaymentMethod::Mada]);

        $this->actingAs($this->client)->put(route('account.subscription.payment-method'), ['payment_method' => 'apple_pay'])->assertSessionHas('status');

        $this->assertSame(PaymentMethod::ApplePay, $subscription->fresh()->payment_method);
    }

    public function test_a_method_that_is_not_enabled_or_does_not_exist_is_refused(): void
    {
        $subscription = $this->subscription(['payment_method' => PaymentMethod::Mada]);

        $this->actingAs($this->client)->put(route('account.subscription.payment-method'), ['payment_method' => 'tabby'])->assertSessionHas('error');
        $this->actingAs($this->client)->put(route('account.subscription.payment-method'), ['payment_method' => 'bitcoin'])->assertSessionHasErrors('payment_method');

        $this->assertSame(PaymentMethod::Mada, $subscription->fresh()->payment_method);
    }

    public function test_switching_to_an_instalment_method_is_blocked_while_renewal_is_on(): void
    {
        config(['itizan.payments.methods.tabby' => true]);
        $subscription = $this->subscription(['payment_method' => PaymentMethod::Mada, 'auto_renew' => true]);

        $this->actingAs($this->client)->put(route('account.subscription.payment-method'), ['payment_method' => 'tabby'])
            ->assertSessionHas('error', 'ألغِ التجديد التلقائي أولًا، لأن الدفع بالتقسيط لا يدعمه.');

        $this->assertSame(PaymentMethod::Mada, $subscription->fresh()->payment_method);
    }

    public function test_without_a_current_subscription_management_endpoints_do_nothing(): void
    {
        $this->actingAs($this->client)->delete(route('account.subscription.auto-renew.disable'))->assertNotFound();
        $this->actingAs($this->client)->put(route('account.subscription.payment-method'), ['payment_method' => 'mada'])->assertNotFound();
    }

    public function test_an_expired_subscription_cannot_be_managed(): void
    {
        Subscription::factory()->expired()->create(['user_id' => $this->client->id, 'auto_renew' => true]);

        $this->actingAs($this->client)->delete(route('account.subscription.auto-renew.disable'))->assertNotFound();
    }

    public function test_one_client_cannot_change_another_clients_subscription(): void
    {
        $theirs = $this->subscribe($this->client(), ['auto_renew' => true]);

        $this->actingAs($this->client)->delete(route('account.subscription.auto-renew.disable'))->assertNotFound();

        $this->assertTrue($theirs->fresh()->auto_renew);
    }

    public function test_the_receipt_list_is_paginated_and_shows_refunds(): void
    {
        $subscription = $this->subscription();
        foreach (range(1, 12) as $i) {
            $this->paidPayment($subscription, ['paid_at' => now()->subDays($i)]);
        }
        Payment::factory()->create(['user_id' => $this->client->id, 'subscription_id' => $subscription->id, 'status' => PaymentStatus::Pending]);

        $response = $this->actingAs($this->client)->get(route('account.subscription.show'))->assertOk();

        $this->assertSame(10, $response->viewData('payments')->count());
        $this->assertSame(12, $response->viewData('payments')->total(), 'pending payments are not receipts');
    }

    public function test_the_auto_renew_consent_ledger_is_append_only(): void
    {
        $this->subscription();
        $this->actingAs($this->client)->delete(route('account.subscription.auto-renew.disable'));
        $this->actingAs($this->client)->post(route('account.subscription.auto-renew.enable'), ['auto_renew_consent' => '1']);

        $records = ConsentRecord::where('type', 'auto_renewal')->orderBy('id')->get();
        $this->assertSame([false, true], $records->pluck('granted')->all());
        $this->assertNull(ConsentRecord::UPDATED_AT);
        $this->assertFalse(Schema::hasColumn('consent_records', 'deleted_at'));
    }

    public function test_staff_cannot_use_the_client_subscription_screens(): void
    {
        foreach ([Role::Admin, Role::Support, Role::Doctor] as $role) {
            $this->actingAs($this->staff($role))->get(route('account.subscription.show'))->assertForbidden();
        }
    }
}
