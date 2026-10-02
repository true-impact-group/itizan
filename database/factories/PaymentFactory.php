<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Order matters: the subscription is created first, then the payment belongs to its client.
            'subscription_id' => Subscription::factory(),
            'user_id' => fn (array $attributes) => Subscription::find($attributes['subscription_id'])->user_id,
            'amount_halalas' => 99900,
            'vat_halalas' => 13030,
            'currency' => 'SAR',
            'method' => PaymentMethod::Mada,
            'status' => PaymentStatus::Pending,
            'provider' => 'sandbox',
            'provider_reference' => 'sbx_'.Str::lower(Str::random(24)),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
            'invoice_number' => 'ITZ-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
        ]);
    }
}
