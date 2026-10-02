<?php

namespace Database\Factories;

use App\Models\Pharmacy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pharmacy>
 */
class PharmacyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'صيدلية '.fake()->lastName(),
            'license_number' => 'PH-'.fake()->unique()->numerify('######'),
            'city' => 'الرياض',
            'phone' => '0112345678',
            'is_active' => true,
        ];
    }
}
