<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+9665'.fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password1'),
            'role' => Role::Client,
            'gender' => fake()->randomElement(Gender::cases()),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-20 years')->format('Y-m-d'),
            'city' => 'الرياض',
            'locale' => 'ar',
            'is_active' => true,
            'pharmacy_id' => null,
            'primary_practitioner_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function admin(): static
    {
        return $this->role(Role::Admin);
    }

    public function support(): static
    {
        return $this->role(Role::Support);
    }

    public function careTeam(): static
    {
        return $this->role(Role::CareTeam);
    }

    public function delivery(): static
    {
        return $this->role(Role::Delivery);
    }

    public function female(): static
    {
        return $this->state(fn () => ['gender' => Gender::Female]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withoutEmail(): static
    {
        return $this->state(fn () => ['email' => null]);
    }
}
