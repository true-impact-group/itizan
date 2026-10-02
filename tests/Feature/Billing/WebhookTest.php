<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $subscription = Subscription::factory()->pending()->create();
        $this->payment = Payment::factory()->create(['user_id' => $subscription->user_id, 'subscription_id' => $subscription->id, 'provider_reference' => 'sbx_ref_123']);
    }

    private function send(array|string $payload, ?string $signature = 'auto', array $headers = [])
    {
        $body = is_array($payload) ? json_encode($payload) : $payload;
        $signature = $signature === 'auto' ? hash_hmac('sha256', $body, 'test-webhook-secret') : $signature;

        return $this->call('POST', route('webhooks.payments'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            ...($signature !== null ? ['HTTP_X_SIGNATURE' => $signature] : []),
            ...$headers,
        ], $body);
    }

    public function test_a_signed_paid_notification_activates_the_subscription(): void
    {
        Notification::fake();

        $this->send(['reference' => 'sbx_ref_123', 'status' => 'paid'])->assertOk()->assertJson(['received' => true]);

        $this->assertSame(PaymentStatus::Paid, $this->payment->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $this->payment->subscription->fresh()->status);
        $this->assertNotNull($this->payment->fresh()->invoice_number);
    }

    public function test_the_webhook_needs_no_csrf_token_or_session(): void
    {
        // Sent from the provider's servers: there is no browser session to hold a CSRF token.
        $this->send(['reference' => 'sbx_ref_123', 'status' => 'paid'])->assertOk();
    }

    public function test_the_same_delivery_repeated_changes_nothing_the_second_time(): void
    {
        Notification::fake();

        $this->send(['reference' => 'sbx_ref_123', 'status' => 'paid'])->assertOk();
        $first = $this->payment->fresh();
        $endsAt = $first->subscription->ends_at;

        $this->travel(2)->days();
        $this->send(['reference' => 'sbx_ref_123', 'status' => 'paid'])->assertOk();

        $second = $this->payment->fresh();
        $this->assertSame($first->invoice_number, $second->invoice_number);
        $this->assertTrue($first->paid_at->equalTo($second->paid_at));
        $this->assertTrue($endsAt->equalTo($second->subscription->fresh()->ends_at), 'the period is not extended by a retry');
        Notification::assertSentToTimes($this->payment->user, GenericNotification::class, 1);
        $this->assertSame(1, Payment::whereNotNull('invoice_number')->count());
    }

    public function test_a_wrong_or_missing_signature_is_rejected_and_changes_nothing(): void
    {
        $body = ['reference' => 'sbx_ref_123', 'status' => 'paid'];

        $this->send($body, 'deadbeef')->assertStatus(401);
        $this->send($body, null)->assertStatus(401);
        $this->send($body, '')->assertStatus(401);
        $this->send($body, hash_hmac('sha256', json_encode($body), 'wrong-secret'))->assertStatus(401);

        $this->assertSame(PaymentStatus::Pending, $this->payment->fresh()->status);
    }

    public function test_a_signature_over_a_different_body_is_rejected(): void
    {
        $signedForFailed = hash_hmac('sha256', json_encode(['reference' => 'sbx_ref_123', 'status' => 'failed']), 'test-webhook-secret');

        $this->send(['reference' => 'sbx_ref_123', 'status' => 'paid'], $signedForFailed)->assertStatus(401);
        $this->assertSame(PaymentStatus::Pending, $this->payment->fresh()->status);
    }

    public function test_without_a_configured_secret_every_webhook_is_refused_even_with_an_empty_signature(): void
    {
        config(['itizan.payments.webhook_secret' => '']);

        $this->send(['reference' => 'sbx_ref_123', 'status' => 'paid'], hash_hmac('sha256', '{"reference":"sbx_ref_123","status":"paid"}', ''))->assertStatus(401);
        $this->assertSame(PaymentStatus::Pending, $this->payment->fresh()->status);
    }

    public function test_a_validly_signed_but_malformed_body_is_a_bad_request(): void
    {
        $this->send('this is not json')->assertStatus(400);
        $this->send('"just a string"')->assertStatus(400);
    }

    public function test_an_unknown_reference_is_acknowledged_and_ignored(): void
    {
        $this->send(['reference' => 'sbx_does_not_exist', 'status' => 'paid'])->assertOk();

        $this->assertSame(PaymentStatus::Pending, $this->payment->fresh()->status);
    }

    public function test_an_unknown_status_is_ignored(): void
    {
        $this->send(['reference' => 'sbx_ref_123', 'status' => 'mystery'])->assertOk();

        $this->assertSame(PaymentStatus::Pending, $this->payment->fresh()->status);
    }

    public function test_a_failure_notification_fails_the_payment_and_closes_the_pending_subscription(): void
    {
        $this->send(['reference' => 'sbx_ref_123', 'status' => 'failed', 'reason' => 'insufficient_funds'])->assertOk();

        $payment = $this->payment->fresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('insufficient_funds', $payment->failure_reason);
        $this->assertSame(SubscriptionStatus::Cancelled, $payment->subscription->fresh()->status);
    }

    public function test_a_late_failure_message_never_downgrades_a_payment_that_already_succeeded(): void
    {
        $this->send(['reference' => 'sbx_ref_123', 'status' => 'paid']);
        $this->send(['reference' => 'sbx_ref_123', 'status' => 'failed']);

        $payment = $this->payment->fresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(SubscriptionStatus::Active, $payment->subscription->fresh()->status);
    }

    public function test_a_paid_message_after_a_failure_still_gives_the_client_their_subscription(): void
    {
        $this->send(['reference' => 'sbx_ref_123', 'status' => 'failed']);
        $this->send(['reference' => 'sbx_ref_123', 'status' => 'paid']);

        $payment = $this->payment->fresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(SubscriptionStatus::Active, $payment->subscription->fresh()->status);
    }

    public function test_a_refunded_payment_cannot_be_reactivated_by_a_replayed_paid_message(): void
    {
        $this->send(['reference' => 'sbx_ref_123', 'status' => 'paid']);
        $this->payment->fresh()->update(['status' => PaymentStatus::Refunded]);
        $this->payment->subscription->update(['status' => SubscriptionStatus::Cancelled, 'cancel_reason' => 'refunded']);

        $this->send(['reference' => 'sbx_ref_123', 'status' => 'paid'])->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $this->payment->fresh()->status);
        $this->assertSame(SubscriptionStatus::Cancelled, $this->payment->subscription->fresh()->status);
    }

    public function test_the_webhook_is_rate_limited(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->send(['reference' => 'x', 'status' => 'paid'], 'bad');
        }

        $this->send(['reference' => 'x', 'status' => 'paid'], 'bad')->assertStatus(429);
    }

    public function test_the_webhook_route_is_post_only(): void
    {
        $this->get(route('webhooks.payments'))->assertStatus(405);
    }
}
