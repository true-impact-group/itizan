<?php

namespace Tests\Concerns;

use App\Enums\AssessmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\MedicalAssessment;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\Practitioner;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\FaqSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\ProductSeeder;

/** Small builders so tests read as scenarios instead of setup noise. */
trait CreatesFixtures
{
    protected function client(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function staff(Role $role, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    protected function admin(): User
    {
        return $this->staff(Role::Admin);
    }

    /** A verified doctor who is bookable at any time of day. */
    protected function doctor(array $practitioner = [], array $user = []): Practitioner
    {
        return Practitioner::factory()
            ->alwaysAvailable()
            ->for(User::factory()->role(Role::Doctor)->state($user), 'user')
            ->create($practitioner);
    }

    protected function dietitian(array $practitioner = []): Practitioner
    {
        return Practitioner::factory()->dietitian()->create($practitioner);
    }

    protected function pharmacist(?Pharmacy $pharmacy = null): User
    {
        $pharmacy ??= Pharmacy::factory()->create();

        return User::factory()->role(Role::Pharmacy)->create(['pharmacy_id' => $pharmacy->id]);
    }

    protected function seedCatalog(): void
    {
        $this->seed([PackageSeeder::class, PageSeeder::class, FaqSeeder::class, ProductSeeder::class]);
    }

    protected function subscribe(User $client, array $attributes = [], ?Package $package = null): Subscription
    {
        $package ??= Package::factory()->create();

        return Subscription::factory()->create([
            'user_id' => $client->id,
            'package_id' => $package->id,
            'followups_included' => $package->included_followups,
            'price_halalas' => $package->price_halalas,
            ...$attributes,
        ]);
    }

    protected function paidPayment(Subscription $subscription, array $attributes = []): Payment
    {
        return Payment::factory()->paid()->create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'status' => PaymentStatus::Paid,
            ...$attributes,
        ]);
    }

    /** A client whose assessment the doctor approved (needed before plans and prescriptions), subscribed by default. */
    protected function approvedClient(array $attributes = [], bool $subscribed = true): User
    {
        $client = $this->client($attributes);
        MedicalAssessment::factory()->approved()->create(['user_id' => $client->id]);
        if ($subscribed) {
            $this->subscribe($client);
        }

        return $client;
    }

    protected function submittedAssessment(User $client, array $attributes = []): MedicalAssessment
    {
        return MedicalAssessment::factory()->create(['user_id' => $client->id, 'status' => AssessmentStatus::Submitted, ...$attributes]);
    }
}
