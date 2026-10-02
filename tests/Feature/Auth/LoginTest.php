<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_client_signs_in_with_their_mobile_number_in_any_format(): void
    {
        $client = $this->client(['phone' => '+966551234567']);

        $this->post(route('login'), ['login' => '055 123 4567', 'password' => 'password1'])
            ->assertRedirect(route('account.dashboard'));

        $this->assertAuthenticatedAs($client);
        $this->assertNotNull($client->fresh()->last_login_at);
    }

    public function test_client_can_also_sign_in_with_their_email_whatever_the_capitalisation(): void
    {
        $client = $this->client(['email' => 'mona@example.com']);

        $this->post(route('login'), ['login' => 'Mona@Example.com', 'password' => 'password1'])->assertRedirect();

        $this->assertAuthenticatedAs($client);
    }

    public function test_each_role_lands_on_its_own_home(): void
    {
        $expected = [
            Role::Admin->value => 'admin.dashboard',
            Role::Support->value => 'admin.dashboard',
            Role::CareTeam->value => 'care.dashboard',
            Role::Delivery->value => 'delivery.orders.index',
            Role::Client->value => 'account.dashboard',
        ];

        foreach ($expected as $role => $route) {
            $user = $this->staff(Role::from($role));

            $this->post(route('login'), ['login' => $user->phone, 'password' => 'password1'])->assertRedirect(route($route));
            $this->post(route('logout'));
        }

        $doctor = $this->doctor();
        $this->post(route('login'), ['login' => $doctor->user->phone, 'password' => 'password1'])->assertRedirect(route('practitioner.dashboard'));
    }

    public function test_wrong_password_gives_one_generic_message_and_does_not_sign_in(): void
    {
        $client = $this->client();

        $this->post(route('login'), ['login' => $client->phone, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['login' => 'بيانات الدخول غير صحيحة.']);

        $this->assertGuest();
    }

    public function test_unknown_account_gets_the_same_message_as_a_wrong_password(): void
    {
        $this->post(route('login'), ['login' => '0500000099', 'password' => 'whatever1'])
            ->assertSessionHasErrors(['login' => 'بيانات الدخول غير صحيحة.']);
    }

    public function test_deactivated_accounts_cannot_sign_in(): void
    {
        $client = $this->client(['is_active' => false]);

        $this->post(route('login'), ['login' => $client->phone, 'password' => 'password1'])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_an_account_deactivated_mid_session_is_signed_out_on_the_next_request(): void
    {
        $client = $this->client();
        $this->actingAs($client);
        $client->forceFill(['is_active' => false])->save();

        $this->get(route('account.dashboard'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $client = $this->client();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['login' => $client->phone, 'password' => 'nope']);
        }

        $this->post(route('login'), ['login' => $client->phone, 'password' => 'password1'])->assertStatus(429);
    }

    public function test_session_id_is_regenerated_on_login(): void
    {
        $client = $this->client();
        $this->get(route('login'));
        $before = session()->getId();

        $this->post(route('login'), ['login' => $client->phone, 'password' => 'password1']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->client())->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_guests_are_sent_to_login_and_returned_after_signing_in(): void
    {
        $client = $this->client();

        $this->get(route('account.plan.show'))->assertRedirect(route('login'));

        $this->post(route('login'), ['login' => $client->phone, 'password' => 'password1'])
            ->assertRedirect(route('account.plan.show'));
    }

    public function test_password_reset_request_never_reveals_whether_an_email_exists(): void
    {
        Notification::fake();
        $this->client(['email' => 'real@example.com']);

        $known = $this->post(route('password.email'), ['email' => 'real@example.com']);
        $unknown = $this->post(route('password.email'), ['email' => 'ghost@example.com']);

        $known->assertSessionHas('status');
        $unknown->assertSessionHas('status', session('status'));
        Notification::assertSentToTimes(User::where('email', 'real@example.com')->first(), ResetPassword::class, 1);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $client = $this->client(['email' => 'real@example.com']);
        $token = Password::createToken($client);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'real@example.com', 'password' => 'NewSecret99', 'password_confirmation' => 'NewSecret99',
        ])->assertRedirect(route('login'));

        $this->post(route('login'), ['login' => $client->phone, 'password' => 'NewSecret99'])->assertRedirect();
        $this->assertAuthenticatedAs($client);
    }

    public function test_password_reset_fails_with_a_bad_token(): void
    {
        $this->client(['email' => 'real@example.com']);

        $this->post(route('password.update'), [
            'token' => 'bad-token', 'email' => 'real@example.com', 'password' => 'NewSecret99', 'password_confirmation' => 'NewSecret99',
        ])->assertSessionHasErrors('email');
    }
}
