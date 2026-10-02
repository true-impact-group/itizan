<?php

namespace Database\Factories;

use App\Models\Measurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Measurement>
 */
class MeasurementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'measured_on' => today(),
            'weight_kg' => 90.0,
            'waist_cm' => 100,
            'source' => 'client',
        ];
    }
}
