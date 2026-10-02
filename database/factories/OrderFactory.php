<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Requested,
            'subtotal_halalas' => 4500,
            'delivery_fee_halalas' => 0,
            'total_halalas' => 4500,
            'free_delivery' => true,
            'delivery_city' => 'الرياض',
            'delivery_address' => 'حي الملقا، شارع الأمير محمد بن سلمان',
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
