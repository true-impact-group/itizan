<?php

namespace Database\Factories;

use App\Enums\PractitionerType;
use App\Enums\Role;
use App\Models\Practitioner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Practitioner>
 */
class PractitionerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(Role::Doctor),
            'type' => PractitionerType::Doctor,
            'name_en' => fake()->name(),
            'specialty_ar' => 'استشاري غدد صماء وسمنة',
            'specialty_en' => 'Endocrinology & Obesity Consultant',
            'license_number' => 'SCFHS-'.fake()->unique()->numerify('#######'),
            'license_authority' => 'SCFHS',
            'license_verified_at' => now(),
            'license_expires_on' => now()->addYear()->toDateString(),
            'bio_ar' => 'خبرة في إدارة الوزن والسمنة.',
            'bio_en' => 'Experienced in weight and obesity management.',
            'years_experience' => 10,
            'accepts_instant' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function dietitian(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory()->role(Role::Dietitian),
            'type' => PractitionerType::Dietitian,
            'specialty_ar' => 'أخصائي تغذية علاجية',
            'specialty_en' => 'Clinical Dietitian',
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['license_verified_at' => null]);
    }

    public function instant(): static
    {
        return $this->state(fn () => ['accepts_instant' => true]);
    }

    /** Available every day, all day, so booking tests never depend on the time of day. */
    public function alwaysAvailable(): static
    {
        return $this->afterCreating(function (Practitioner $practitioner) {
            foreach (range(0, 6) as $weekday) {
                $practitioner->availabilities()->create([
                    'weekday' => $weekday, 'start_time' => '00:00', 'end_time' => '23:59', 'slot_minutes' => 20,
                ]);
            }
        });
    }
}
