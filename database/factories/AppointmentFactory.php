<?php

namespace Database\Factories;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\User;
use App\Support\FtmsMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $reference = FtmsMeeting::newReference();

        return [
            'user_id' => User::factory(),
            'practitioner_id' => Practitioner::factory(),
            'type' => AppointmentType::FollowUp,
            'mode' => AppointmentMode::Video,
            'status' => AppointmentStatus::Confirmed,
            'scheduled_at' => now()->addDays(2)->setMinute(0)->setSecond(0),
            'is_instant' => false,
            'duration_minutes' => 20,
            'ftms_reference' => $reference,
            'meeting_url' => FtmsMeeting::urlFor($reference),
        ];
    }

    public function freeIntro(): static
    {
        return $this->state(fn () => ['type' => AppointmentType::FreeIntro]);
    }

    public function instantRequest(): static
    {
        return $this->state(fn () => [
            'practitioner_id' => null,
            'is_instant' => true,
            'status' => AppointmentStatus::Requested,
            'scheduled_at' => now(),
        ]);
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
