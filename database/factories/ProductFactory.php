<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'name_ar' => 'فيتامين د',
            'name_en' => 'Vitamin D',
            'type' => ProductType::Supplement,
            'requires_prescription' => false,
            'price_halalas' => 4500,
            'in_stock' => true,
            'is_active' => true,
        ];
    }

    public function medication(): static
    {
        return $this->state(fn () => [
            'name_ar' => 'دواء لإدارة الوزن',
            'name_en' => 'Weight management medication',
            'type' => ProductType::Medication,
            'requires_prescription' => true,
            'price_halalas' => 45000,
        ]);
    }
}
