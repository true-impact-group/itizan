<?php

namespace Database\Factories;

use App\Enums\ContactRequestStatus;
use App\Enums\ContactTarget;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'target' => ContactTarget::CareTeam,
            'reason' => 'أحتاج إلى التواصل بخصوص متابعتي.',
            'priority' => 'normal',
            'source' => 'client',
            'status' => ContactRequestStatus::Open,
        ];
    }
}
