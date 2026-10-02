<?php

namespace Database\Factories;

use App\Enums\AssessmentStatus;
use App\Models\MedicalAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalAssessment>
 */
class MedicalAssessmentFactory extends Factory
{
    public function definition(): array
    {
        $safety = array_fill_keys(array_keys(config('itizan.safety_questions')), 'no');

        return [
            'user_id' => User::factory(),
            'status' => AssessmentStatus::Submitted,
            'height_cm' => 170,
            'weight_kg' => 95.5,
            'waist_cm' => 105,
            'bmi' => 33.0,
            'target_weight_kg' => 80,
            'medical_history' => 'لا يوجد',
            'chronic_conditions' => 'لا يوجد',
            'current_medications' => 'لا يوجد',
            'allergies' => 'لا يوجد',
            'goals' => 'خسارة وزن صحية',
            'safety_answers' => $safety,
            'has_red_flags' => false,
            'submitted_at' => now()->subHour(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => AssessmentStatus::Draft, 'submitted_at' => null]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => AssessmentStatus::Approved, 'reviewed_at' => now()]);
    }

    public function withRedFlag(): static
    {
        return $this->state(fn () => [
            'has_red_flags' => true,
            'safety_answers' => [...array_fill_keys(array_keys(config('itizan.safety_questions')), 'no'), 'pancreatitis' => 'yes'],
        ]);
    }
}
