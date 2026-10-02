<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'package_id' => Package::factory(),
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addMonths(6),
            'auto_renew' => false,
            'payment_method' => PaymentMethod::Mada,
            'price_halalas' => 99900,
            'followups_included' => 3,
            'followups_used' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => SubscriptionStatus::Pending, 'starts_at' => now(), 'ends_at' => now()->addMonths(6)]);
    }

    public function endingIn(int $days): static
    {
        return $this->state(fn () => ['ends_at' => now()->addDays($days)]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Expired,
            'starts_at' => now()->subMonths(7),
            'ends_at' => now()->subDay(),
        ]);
    }
}
