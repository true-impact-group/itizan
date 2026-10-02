<?php

namespace Database\Factories;

use App\Enums\LabStatus;
use App\Models\LabRequest;
use App\Models\Practitioner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabRequest>
 */
class LabRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'practitioner_id' => Practitioner::factory(),
            'name' => 'سكر تراكمي HbA1c',
            'status' => LabStatus::Requested,
        ];
    }

    public function uploaded(): static
    {
        return $this->state(fn () => ['status' => LabStatus::Uploaded, 'result_uploaded_at' => now()]);
    }
}
