<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\Practitioner;
use App\Models\User;
use App\Models\WeightPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeightPlan>
 */
class WeightPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'practitioner_id' => Practitioner::factory(),
            'status' => PlanStatus::Active,
            'start_weight_kg' => 95.0,
            'target_weight_kg' => 80.0,
            'start_waist_cm' => 105,
            'doctor_notes' => 'نبدأ بخطوات صغيرة ومستمرة.',
            'next_steps' => ['سجّل وزنك أسبوعيًا', 'اشرب الماء بانتظام'],
            'starts_on' => today(),
        ];
    }
}
