<?php

namespace Tests\Feature\Console;

use App\Enums\AppointmentStatus;
use App\Enums\PractitionerType;
use App\Enums\PrescriptionStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\Prescription;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Notifications\DoseReminderNotification;
use App\Notifications\RenewalReminderNotification;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduledCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
    }

    // ---- dose reminders ------------------------------------------------------------------------------

    public function test_a_reminder_is_sent_for_a_dose_due_within_the_lead_time_and_only_once(): void
    {
        Notification::fake();
        $rx = Prescription::factory()->create(['next_dose_at' => now()->addMinutes(20)]);

        $this->artisan('itizan:dose-reminders')->assertSuccessful()->expectsOutput('Dose reminders queued: 1');
        $this->artisan('itizan:dose-reminders')->assertSuccessful()->expectsOutput('Dose reminders queued: 0');

        Notification::assertSentToTimes($rx->user, DoseReminderNotification::class, 1);
        $this->assertTrue($rx->fresh()->reminder_sent_for->equalTo($rx->next_dose_at));
    }

    public function test_doses_that_are_not_due_yet_or_long_overdue_are_not_reminded(): void
    {
        Notification::fake();
        Prescription::factory()->create(['next_dose_at' => now()->addHours(3)]);
        Prescription::factory()->create(['next_dose_at' => now()->subHours(20)]);

        $this->artisan('itizan:dose-reminders')->expectsOutput('Dose reminders queued: 0');

        Notification::assertNothingSent();
    }

    public function test_stopped_prescriptions_get_no_reminders(): void
    {
        Notification::fake();
        Prescription::factory()->create(['status' => PrescriptionStatus::Stopped, 'next_dose_at' => now()->addMinutes(10)]);
        Prescription::factory()->create(['status' => PrescriptionStatus::Completed, 'next_dose_at' => null]);

        $this->artisan('itizan:dose-reminders');

        Notification::assertNothingSent();
    }

    public function test_a_new_reminder_is_sent_for_the_next_dose_after_the_schedule_advances(): void
    {
        Notification::fake();
        $rx = Prescription::factory()->create(['next_dose_at' => now()->addMinutes(20), 'interval_days' => 1]);

        $this->artisan('itizan:dose-reminders');
        $rx->update(['next_dose_at' => now()->addDay()->addMinutes(20)]);
        $this->travel(1)->days();
        $this->artisan('itizan:dose-reminders');

        Notification::assertSentToTimes($rx->user, DoseReminderNotification::class, 2);
    }

    public function test_reminders_are_queued_not_sent_inline(): void
    {
        Queue::fake();
        Prescription::factory()->create(['next_dose_at' => now()->addMinutes(20)]);

        $this->artisan('itizan:dose-reminders');

        Queue::assertPushed(SendQueuedNotifications::class);
    }

    public function test_reminders_reach_people_without_email_in_app_and_with_email_by_mail_too(): void
    {
        $withoutEmail = User::factory()->withoutEmail()->create();
        $withEmail = $this->client();
        $rx = new Prescription;
        $notification = new DoseReminderNotification(Prescription::factory()->create());

        $this->assertSame(['database'], $notification->via($withoutEmail));
        $this->assertSame(['database', 'mail'], $notification->via($withEmail));
    }

    // ---- appointment reminders ------------------------------------------------------------------------------

    public function test_a_reminder_goes_out_for_a_confirmed_appointment_starting_within_the_hour(): void
    {
        Notification::fake();
        $soon = Appointment::factory()->create(['scheduled_at' => now()->addMinutes(40)]);
        Appointment::factory()->create(['scheduled_at' => now()->addHours(3)]);
        Appointment::factory()->status(AppointmentStatus::Cancelled)->create(['scheduled_at' => now()->addMinutes(30)]);
        Appointment::factory()->status(AppointmentStatus::Requested)->create(['scheduled_at' => now()->addMinutes(30)]);

        $this->artisan('itizan:appointment-reminders')->expectsOutput('Appointment reminders queued: 1');
        $this->artisan('itizan:appointment-reminders')->expectsOutput('Appointment reminders queued: 0');

        Notification::assertSentTo($soon->user, AppointmentNotification::class);
        Notification::assertCount(1);
        $this->assertNotNull($soon->fresh()->reminder_sent_at);
    }

    public function test_the_reminder_text_carries_the_time_but_no_clinical_detail(): void
    {
        $appointment = Appointment::factory()->create(['scheduled_at' => now()->addMinutes(40), 'client_notes' => 'سبب زيارة سري']);
        $notification = new AppointmentNotification($appointment, reminder: true);

        $payload = json_encode($notification->toArray($appointment->user), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('تذكير بموعد استشارتك', $payload);
        $this->assertStringNotContainsString('سري', $payload);
    }

    // ---- subscriptions ------------------------------------------------------------------------------

    public function test_finished_subscriptions_expire_and_running_ones_are_untouched(): void
    {
        $finished = $this->subscribe($this->client(), ['starts_at' => now()->subMonths(7), 'ends_at' => now()->subHour()]);
        $running = $this->subscribe($this->client(), ['ends_at' => now()->addMonths(3)]);

        $this->artisan('itizan:process-subscriptions')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::Expired, $finished->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $running->fresh()->status);
    }

    public function test_abandoned_checkouts_older_than_two_days_are_closed(): void
    {
        $old = Subscription::factory()->pending()->create(['created_at' => now()->subDays(3)]);
        $recent = Subscription::factory()->pending()->create(['created_at' => now()->subHours(3)]);

        $this->artisan('itizan:process-subscriptions');

        $this->assertSame(SubscriptionStatus::Cancelled, $old->fresh()->status);
        $this->assertSame('abandoned', $old->fresh()->cancel_reason);
        $this->assertSame(SubscriptionStatus::Pending, $recent->fresh()->status);
    }

    public function test_a_renewal_reminder_is_sent_once_a_week_before_the_end(): void
    {
        Notification::fake();
        $ending = $this->subscribe($this->client(), ['ends_at' => now()->addDays(5)]);
        $this->subscribe($this->client(), ['ends_at' => now()->addDays(30)]);

        $this->artisan('itizan:process-subscriptions')->assertSuccessful();
        $this->artisan('itizan:process-subscriptions')->assertSuccessful();

        Notification::assertSentToTimes($ending->user, RenewalReminderNotification::class, 1);
        Notification::assertCount(1);
        $this->assertNotNull($ending->fresh()->renewal_reminded_at);
    }

    public function test_the_renewal_reminder_mentions_the_end_date_and_links_to_the_subscription_page(): void
    {
        $subscription = $this->subscribe($this->client(), ['ends_at' => now()->addDays(5)]);
        $notification = new RenewalReminderNotification($subscription);

        $data = $notification->toArray($subscription->user);

        $this->assertStringContainsString('اشتراكك يقارب على الانتهاء', $data['title']);
        $this->assertSame(route('account.subscription.show'), $data['url']);
    }

    // ---- schedule registration ------------------------------------------------------------------------------

    public function test_the_commands_are_scheduled_without_overlap(): void
    {
        $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");

        foreach (['itizan:dose-reminders', 'itizan:appointment-reminders', 'itizan:process-subscriptions'] as $command) {
            $this->assertStringContainsString($command, $events);
        }
        foreach (app(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, 'itizan:')) {
                $this->assertTrue($event->withoutOverlapping, "{$event->command} must not overlap");
                $this->assertTrue($event->onOneServer, "{$event->command} must run on one server");
            }
        }
    }

    // ---- create-admin ------------------------------------------------------------------------------

    public function test_the_create_admin_command_asks_for_the_password_and_creates_an_administrator(): void
    {
        $this->artisan('itizan:create-admin', ['name' => 'مدير أول', 'phone' => '0551234567', '--email' => 'root@example.com'])
            ->expectsQuestion('Password', 'Sup3rStr0ngPassw0rd')
            ->expectsOutputToContain('Administrator created')
            ->assertSuccessful();

        $admin = User::where('phone', '+966551234567')->firstOrFail();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('Sup3rStr0ngPassw0rd', $admin->password));
    }

    public function test_the_create_admin_command_refuses_weak_passwords_and_duplicates(): void
    {
        $this->artisan('itizan:create-admin', ['name' => 'مدير', 'phone' => '0551234567'])
            ->expectsQuestion('Password', 'short1')
            ->assertFailed();

        $this->client(['phone' => '+966551234567']);
        $this->artisan('itizan:create-admin', ['name' => 'مدير', 'phone' => '0551234567'])
            ->expectsQuestion('Password', 'Sup3rStr0ngPassw0rd')
            ->assertFailed();

        $this->assertSame(0, User::where('role', 'admin')->count());
    }

    public function test_the_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DemoSeeder must never run in production');

        // Called directly: `db:seed` would stop at its own "are you sure" prompt in production before reaching the seeder.
        (new DemoSeeder)->run();
    }

    public function test_the_demo_seeder_builds_a_working_demo_dataset(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(9, User::count());
        $this->assertSame(2, Practitioner::verified()->ofType(PractitionerType::Doctor)->count());
        $this->assertNotNull(User::where('phone', '+966500000010')->first()->currentSubscription);

        $this->post(route('login'), ['login' => '0500000010', 'password' => 'password1'])->assertRedirect(route('account.dashboard'));
        $this->get(route('account.dashboard'))->assertOk();
    }
}
