<?php

namespace Database\Factories;

use App\Enums\PrescriptionStatus;
use App\Models\Practitioner;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'practitioner_id' => Practitioner::factory(),
            'medication_name' => 'دواء تجريبي',
            'dose' => '0.25 ملغ',
            'instructions' => 'يؤخذ حسب التعليمات',
            'interval_days' => 7,
            'dose_time' => '09:00',
            'starts_on' => today()->subWeek(),
            'status' => PrescriptionStatus::Active,
            'next_dose_at' => now()->addDay(),
            'pre_dose_questions' => null,
        ];
    }

    public function due(): static
    {
        return $this->state(fn () => ['next_dose_at' => now()->subHour()]);
    }

    public function withQuestion(string $text = 'هل تشعر بغثيان شديد؟', string $alertOn = 'yes'): static
    {
        return $this->state(fn () => ['pre_dose_questions' => [['text' => $text, 'alert_on' => $alertOn]]]);
    }
}
