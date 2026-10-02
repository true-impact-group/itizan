<?php

namespace Database\Factories;

use App\Enums\Severity;
use App\Enums\SideEffectStatus;
use App\Models\SideEffectReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SideEffectReport>
 */
class SideEffectReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symptom' => 'غثيان خفيف بعد الجرعة',
            'severity' => Severity::Mild,
            'occurred_at' => now()->subHours(3),
            'status' => SideEffectStatus::New,
        ];
    }
}
