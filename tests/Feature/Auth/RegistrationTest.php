<?php

namespace Tests\Feature\Auth;

use App\Enums\ConsentType;
use App\Enums\Role;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'منى العلي',
            'phone' => '0551234567',
            'email' => 'mona@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'gender' => 'female',
            'birth_date' => '1992-04-01',
            'city' => 'الرياض',
            'terms' => '1',
            ...$overrides,
        ];
    }

    public function test_a_visitor_registers_with_a_mobile_number_and_lands_on_their_account(): void
    {
        Notification::fake();

        $this->post(route('register'), $this->payload())->assertRedirect(route('account.dashboard'));

        $user = User::where('phone', '+966551234567')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::Client, $user->role);
        $this->assertTrue($user->is_active);
        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_registration_continues_to_the_booking_step_when_started_from_the_free_consultation_button(): void
    {
        $this->get(route('free-consultation'));

        $this->post(route('register'), $this->payload())
            ->assertRedirect(route('account.appointments.create', ['type' => 'free_intro']));
    }

    public function test_phone_number_is_normalised_whatever_format_is_typed(): void
    {
        $this->post(route('register'), $this->payload(['phone' => '٠٥٥١٢٣٤٥٦٧']));

        $this->assertDatabaseHas('users', ['phone' => '+966551234567']);
    }

    public function test_terms_and_privacy_consent_is_recorded_and_marketing_is_separate_and_optional(): void
    {
        $this->post(route('register'), $this->payload());
        $user = User::firstOrFail();

        $this->assertDatabaseHas('consent_records', ['user_id' => $user->id, 'type' => ConsentType::TermsPrivacy->value, 'granted' => true]);
        $this->assertDatabaseMissing('consent_records', ['user_id' => $user->id, 'type' => ConsentType::Marketing->value]);
        $this->assertDatabaseMissing('consent_records', ['user_id' => $user->id, 'type' => ConsentType::MedicalData->value]);
    }

    public function test_marketing_consent_is_only_recorded_when_the_client_opts_in(): void
    {
        $this->post(route('register'), $this->payload(['marketing_consent' => '1']));

        $this->assertSame(1, ConsentRecord::where('type', ConsentType::Marketing->value)->where('granted', true)->count());
    }

    public function test_terms_must_be_accepted(): void
    {
        $this->post(route('register'), $this->payload(['terms' => '0']))->assertSessionHasErrors('terms');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_phone_numbers_are_rejected_even_in_another_format(): void
    {
        $this->client(['phone' => '+966551234567']);

        $this->post(route('register'), $this->payload(['phone' => '0551234567', 'email' => 'other@example.com']))
            ->assertSessionHasErrors('phone');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->client(['email' => 'mona@example.com']);

        $this->post(route('register'), $this->payload(['phone' => '0559999999']))->assertSessionHasErrors('email');
    }

    public function test_invalid_phone_numbers_are_rejected(): void
    {
        $this->post(route('register'), $this->payload(['phone' => '12345']))->assertSessionHasErrors('phone');
    }

    public function test_weak_or_unconfirmed_passwords_are_rejected(): void
    {
        $this->post(route('register'), $this->payload(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post(route('register'), $this->payload(['password_confirmation' => 'Different123']))->assertSessionHasErrors('password');
        $this->post(route('register'), $this->payload(['password' => 'onlyletters', 'password_confirmation' => 'onlyletters']))->assertSessionHasErrors('password');
    }

    public function test_email_is_optional(): void
    {
        $this->post(route('register'), $this->payload(['email' => '']))->assertRedirect(route('account.dashboard'));

        $this->assertNull(User::firstOrFail()->email);
    }

    public function test_a_visitor_cannot_choose_their_own_role_or_activation(): void
    {
        $this->post(route('register'), $this->payload(['role' => 'admin', 'is_active' => false, 'pharmacy_id' => 1]));

        $user = User::firstOrFail();
        $this->assertSame(Role::Client, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->pharmacy_id);
    }

    public function test_city_must_be_one_of_the_supported_cities(): void
    {
        $this->post(route('register'), $this->payload(['city' => 'مدينة وهمية']))->assertSessionHasErrors('city');
    }

    public function test_signed_in_users_are_redirected_away_from_the_registration_page(): void
    {
        $this->actingAs($this->client())->get(route('register'))->assertRedirect(route('account.dashboard'));
    }

    public function test_registration_is_rate_limited_per_address(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('register'), $this->payload(['terms' => '0']));
        }

        $this->post(route('register'), $this->payload())->assertStatus(429);
    }
}
