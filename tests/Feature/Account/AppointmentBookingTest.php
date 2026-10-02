<?php

namespace Tests\Feature\Account;

use App\Enums\AppointmentMode;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\ConsentType;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    private User $client;

    private Practitioner $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        // A Monday morning, so "tomorrow 10:00" is always a valid future slot.
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));

        $this->client = $this->client();
        $this->doctor = $this->doctor();
    }

    private function slot(string $time = '10:00', int $daysAhead = 1): string
    {
        return now()->addDays($daysAhead)->format('Y-m-d').' '.$time;
    }

    private function book(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->client)->post(route('account.appointments.store'), [
            'type' => 'free_intro',
            'mode' => 'video',
            'starts_at' => $this->slot(),
            'acknowledge' => '1',
            ...$overrides,
        ]);
    }

    public function test_booking_page_explains_the_scope_and_how_to_attend_before_confirming(): void
    {
        $this->actingAs($this->client)
            ->get(route('account.appointments.create', ['type' => 'free_intro']))
            ->assertOk()
            ->assertSee('ما الذي تشمله الاستشارة المجانية وكيف تحضرها؟')
            ->assertSee('لا تلزمك الاستشارة بالاشتراك')
            ->assertSee('FTMS')
            ->assertSee('قرار الطبيب بشأن العلاج أو الوصفة يخضع للتقييم الطبي');
    }

    public function test_booking_page_lists_the_available_slots_for_the_chosen_day(): void
    {
        $response = $this->actingAs($this->client)
            ->get(route('account.appointments.create', ['type' => 'free_intro', 'date' => now()->addDay()->toDateString()]))
            ->assertOk();

        // 24h availability, 20-minute slots => 72 slots on a full future day.
        $this->assertSame(72, substr_count($response->getContent(), 'name="starts_at"'));
    }

    public function test_slots_inside_the_minimum_notice_window_are_not_offered(): void
    {
        $response = $this->actingAs($this->client)
            ->get(route('account.appointments.create', ['type' => 'free_intro', 'date' => today()->toDateString()]));

        // Now is 08:00, minimum notice 30 minutes: the 08:00 and 08:20 slots are gone, 08:40 is first.
        $html = $response->getContent();
        $this->assertStringNotContainsString('value="'.$this->slot('08:00', 0).'"', $html);
        $this->assertStringNotContainsString('value="'.$this->slot('08:20', 0).'"', $html);
        $this->assertStringContainsString('value="'.$this->slot('08:40', 0).'"', $html);
        $this->assertStringNotContainsString('value="'.$this->slot('07:00', 0).'"', $html);
    }

    public function test_client_books_the_free_consultation_and_it_is_confirmed_with_a_verified_doctor(): void
    {
        Notification::fake();

        $response = $this->book();

        $appointment = Appointment::firstOrFail();
        $response->assertRedirect(route('account.appointments.show', $appointment));

        $this->assertSame(AppointmentType::FreeIntro, $appointment->type);
        $this->assertSame(AppointmentMode::Video, $appointment->mode);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame($this->doctor->id, $appointment->practitioner_id);
        $this->assertSame($this->client->id, $appointment->user_id);
        $this->assertTrue($appointment->scheduled_at->equalTo(Carbon::parse($this->slot())));
        $this->assertNotNull($appointment->ftms_reference);
        $this->assertStringContainsString($appointment->ftms_reference, $appointment->meeting_url);
        Notification::assertSentTo($this->client, AppointmentNotification::class);
    }

    public function test_telemedicine_consent_is_recorded_when_booking(): void
    {
        $this->book();

        $this->assertDatabaseHas('consent_records', ['user_id' => $this->client->id, 'type' => ConsentType::Telemedicine->value, 'granted' => true]);
    }

    public function test_the_acknowledgement_of_scope_is_required(): void
    {
        $this->book(['acknowledge' => '0'])->assertSessionHasErrors('acknowledge');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_the_second_free_consultation_is_refused(): void
    {
        $this->book()->assertRedirect();
        $this->book(['starts_at' => $this->slot('11:00')])
            ->assertSessionHas('error', 'لقد استفدت من الاستشارة المجانية من قبل.');

        $this->assertSame(1, Appointment::where('type', 'free_intro')->count());
    }

    public function test_the_booking_page_redirects_away_when_the_free_consultation_was_used(): void
    {
        $this->book();

        $this->actingAs($this->client)
            ->get(route('account.appointments.create', ['type' => 'free_intro']))
            ->assertRedirect(route('account.appointments.index'));
    }

    public function test_a_cancelled_free_consultation_can_be_rebooked(): void
    {
        $this->book();
        $first = Appointment::firstOrFail();

        $this->actingAs($this->client)->post(route('account.appointments.cancel', $first))->assertRedirect();
        $this->book(['starts_at' => $this->slot('11:00')])->assertRedirect();

        $this->assertSame(2, Appointment::count());
        $this->assertSame(1, Appointment::where('status', 'confirmed')->count());
    }

    public function test_the_database_itself_refuses_a_second_free_consultation(): void
    {
        Appointment::factory()->freeIntro()->create(['user_id' => $this->client->id, 'practitioner_id' => $this->doctor->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        Appointment::factory()->freeIntro()->create(['user_id' => $this->client->id, 'practitioner_id' => $this->doctor->id, 'scheduled_at' => now()->addDays(3)]);
    }

    public function test_a_taken_slot_cannot_be_booked_by_someone_else(): void
    {
        $this->book()->assertRedirect();

        $other = $this->client();
        $this->book([], $other)->assertSessionHas('error', 'هذا الموعد لم يعد متاحًا، اختر موعدًا آخر.');

        $this->assertSame(1, Appointment::count());
    }

    public function test_the_database_refuses_double_booking_a_practitioner(): void
    {
        $time = now()->addDays(2)->setTime(9, 0);
        Appointment::factory()->create(['practitioner_id' => $this->doctor->id, 'scheduled_at' => $time]);

        $this->expectException(UniqueConstraintViolationException::class);

        Appointment::factory()->create(['practitioner_id' => $this->doctor->id, 'scheduled_at' => $time]);
    }

    public function test_a_cancelled_slot_becomes_bookable_again_at_database_level(): void
    {
        $time = now()->addDays(2)->setTime(9, 0);
        Appointment::factory()->status(AppointmentStatus::Cancelled)->create(['practitioner_id' => $this->doctor->id, 'scheduled_at' => $time]);
        Appointment::factory()->create(['practitioner_id' => $this->doctor->id, 'scheduled_at' => $time]);

        $this->assertSame(2, Appointment::count());
    }

    public function test_a_second_doctor_is_used_when_the_first_is_busy_at_that_time(): void
    {
        $second = $this->doctor();
        Appointment::factory()->create(['practitioner_id' => $this->doctor->id, 'scheduled_at' => Carbon::parse($this->slot())]);

        $this->book()->assertRedirect();

        $this->assertSame($second->id, Appointment::where('user_id', $this->client->id)->value('practitioner_id'));
    }

    public function test_times_in_the_past_or_too_soon_are_refused(): void
    {
        $this->book(['starts_at' => $this->slot('07:00', 0)])->assertSessionHas('error');
        $this->book(['starts_at' => $this->slot('08:20', 0)])->assertSessionHas('error');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_times_beyond_the_booking_horizon_are_refused(): void
    {
        $this->book(['starts_at' => $this->slot('10:00', 40)])->assertSessionHas('error');
    }

    public function test_a_time_that_is_not_on_the_slot_grid_is_refused(): void
    {
        $this->book(['starts_at' => $this->slot('10:07')])->assertSessionHas('error', 'هذا الموعد لم يعد متاحًا، اختر موعدًا آخر.');
    }

    public function test_times_outside_the_doctors_working_hours_are_refused(): void
    {
        Practitioner::query()->delete();
        $partTime = Practitioner::factory()->create();
        $partTime->availabilities()->create(['weekday' => now()->addDay()->dayOfWeek, 'start_time' => '09:00', 'end_time' => '12:00', 'slot_minutes' => 20]);

        $this->book(['starts_at' => $this->slot('15:00')])->assertSessionHas('error');
        $this->book(['starts_at' => $this->slot('11:40')])->assertRedirect();   // last slot of the window
        $this->book(['starts_at' => $this->slot('11:50')], $this->client())->assertSessionHas('error'); // slot would overrun the window
    }

    public function test_a_window_ending_at_2359_really_runs_until_midnight(): void
    {
        $this->book(['starts_at' => $this->slot('23:40')])->assertRedirect();   // the final slot of a 24-hour day
    }

    public function test_unverified_or_expired_practitioners_cannot_be_booked(): void
    {
        Practitioner::query()->delete();
        Practitioner::factory()->alwaysAvailable()->unverified()->create();
        Practitioner::factory()->alwaysAvailable()->create(['license_expires_on' => now()->subDay()]);
        Practitioner::factory()->alwaysAvailable()->create(['is_active' => false]);
        Practitioner::factory()->alwaysAvailable()->dietitian()->create(); // dietitians take no consultations

        $this->book()->assertSessionHas('error');
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_only_clients_can_book(): void
    {
        $this->book([], $this->staff(Role::CareTeam))->assertForbidden();
    }

    public function test_validation_of_mode_and_time_format(): void
    {
        $this->book(['mode' => 'carrier-pigeon'])->assertSessionHasErrors('mode');
        $this->book(['starts_at' => 'tomorrow morning'])->assertSessionHasErrors('starts_at');
        $this->book(['starts_at' => ''])->assertSessionHasErrors('starts_at');
        $this->book(['type' => 'vip'])->assertSessionHasErrors('type');
    }

    // ---- instant consultations ------------------------------------------------------------------------------

    public function test_an_instant_request_waits_in_the_queue_until_a_doctor_claims_it(): void
    {
        $response = $this->book(['instant' => '1', 'starts_at' => '']);

        $appointment = Appointment::firstOrFail();
        $response->assertRedirect(route('account.appointments.show', $appointment));
        $this->assertTrue($appointment->is_instant);
        $this->assertSame(AppointmentStatus::Requested, $appointment->status);
        $this->assertNull($appointment->practitioner_id);
    }

    public function test_the_first_instant_doctor_to_claim_wins_and_the_client_is_notified(): void
    {
        Notification::fake();
        $this->book(['instant' => '1', 'starts_at' => '']);
        $appointment = Appointment::firstOrFail();
        $first = $this->doctor(['accepts_instant' => true]);
        $second = $this->doctor(['accepts_instant' => true]);

        $this->actingAs($first->user)->post(route('practitioner.appointments.claim', $appointment))->assertRedirect();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame($first->id, $appointment->practitioner_id);
        Notification::assertSentTo($this->client, AppointmentNotification::class);

        $this->actingAs($second->user)->post(route('practitioner.appointments.claim', $appointment))->assertForbidden();
        $this->assertSame($first->id, $appointment->fresh()->practitioner_id);
    }

    public function test_a_doctor_who_does_not_take_instant_consultations_cannot_claim(): void
    {
        $this->book(['instant' => '1', 'starts_at' => '']);
        $regular = $this->doctor(['accepts_instant' => false]);

        $this->actingAs($regular->user)->post(route('practitioner.appointments.claim', Appointment::firstOrFail()))
            ->assertSessionHas('error', 'لا يمكنك استلام الاستشارات الفورية.');

        $this->assertNull(Appointment::firstOrFail()->practitioner_id);
    }

    public function test_instant_requests_show_in_the_doctors_queue(): void
    {
        $this->book(['instant' => '1', 'starts_at' => '']);
        $instantDoctor = $this->doctor(['accepts_instant' => true]);

        $this->actingAs($instantDoctor->user)->get(route('practitioner.dashboard'))
            ->assertOk()
            ->assertSee('طلبات استشارة فورية')
            ->assertSee($this->client->name);
    }

    // ---- follow-up consultations and the subscription quota ------------------------------------------------------------------------------

    public function test_a_follow_up_needs_an_active_subscription(): void
    {
        $this->actingAs($this->client)
            ->get(route('account.appointments.create', ['type' => 'follow_up']))
            ->assertRedirect(route('account.subscription.show'));

        $this->book(['type' => 'follow_up'])->assertSessionHas('error', 'تحتاج إلى اشتراك فعّال لحجز استشارة متابعة.');
    }

    public function test_a_follow_up_uses_one_of_the_included_consultations(): void
    {
        $subscription = $this->subscribe($this->client);

        $this->book(['type' => 'follow_up'])->assertRedirect();

        $this->assertSame(1, $subscription->fresh()->followups_used);
        $this->assertSame(2, $subscription->fresh()->remainingFollowups());
        $this->assertSame($subscription->id, Appointment::firstOrFail()->subscription_id);
    }

    public function test_follow_ups_stop_when_the_quota_is_used_up(): void
    {
        $subscription = $this->subscribe($this->client, ['followups_included' => 1, 'followups_used' => 1]);

        $this->book(['type' => 'follow_up'])->assertSessionHas('error', 'استنفدت استشارات المتابعة المشمولة في باقتك.');

        $this->assertSame(1, $subscription->fresh()->followups_used);
    }

    public function test_an_expired_subscription_gives_no_follow_ups(): void
    {
        Subscription::factory()->expired()->create(['user_id' => $this->client->id]);

        $this->book(['type' => 'follow_up'])->assertSessionHas('error');
    }

    public function test_a_failed_booking_does_not_consume_a_follow_up(): void
    {
        $subscription = $this->subscribe($this->client);

        $this->book(['type' => 'follow_up', 'starts_at' => $this->slot('10:07')])->assertSessionHas('error');

        $this->assertSame(0, $subscription->fresh()->followups_used);
    }

    public function test_cancelling_a_follow_up_gives_the_consultation_back(): void
    {
        $subscription = $this->subscribe($this->client);
        $this->book(['type' => 'follow_up']);

        $this->actingAs($this->client)->post(route('account.appointments.cancel', Appointment::firstOrFail()))->assertRedirect();

        $this->assertSame(0, $subscription->fresh()->followups_used);
        $this->assertSame(AppointmentStatus::Cancelled, Appointment::firstOrFail()->status);
    }

    // ---- cancelling / attending ------------------------------------------------------------------------------

    public function test_a_client_cannot_cancel_shortly_before_the_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->client->id,
            'practitioner_id' => $this->doctor->id,
            'scheduled_at' => now()->addHour(),
        ]);

        $this->actingAs($this->client)->post(route('account.appointments.cancel', $appointment))
            ->assertSessionHas('error');

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_a_client_can_cancel_well_in_advance(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->client->id, 'practitioner_id' => $this->doctor->id, 'scheduled_at' => now()->addDay(),
        ]);

        $this->actingAs($this->client)->post(route('account.appointments.cancel', $appointment))->assertRedirect(route('account.appointments.index'));

        $this->assertNotNull($appointment->fresh()->cancelled_at);
    }

    public function test_an_already_cancelled_appointment_cannot_be_cancelled_again(): void
    {
        $appointment = Appointment::factory()->status(AppointmentStatus::Cancelled)->create(['user_id' => $this->client->id, 'practitioner_id' => $this->doctor->id]);

        $this->actingAs($this->client)->post(route('account.appointments.cancel', $appointment))->assertSessionHas('error', 'لا يمكن إلغاء هذا الموعد.');
    }

    public function test_the_join_link_opens_ten_minutes_before_the_start_and_closes_after_the_end(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->client->id, 'practitioner_id' => $this->doctor->id, 'scheduled_at' => now()->addHour(),
        ]);

        $this->actingAs($this->client)->get(route('account.appointments.show', $appointment))
            ->assertDontSee($appointment->meeting_url)
            ->assertSee('يفتح رابط الدخول قبل الموعد بعشر دقائق');

        $this->travelTo($appointment->scheduled_at->copy()->subMinutes(9));
        $this->get(route('account.appointments.show', $appointment))->assertSee($appointment->meeting_url);

        $this->travelTo($appointment->scheduled_at->copy()->addMinutes(25));
        $this->get(route('account.appointments.show', $appointment))->assertDontSee($appointment->meeting_url);
    }

    public function test_appointment_list_is_paginated_and_only_shows_own_appointments(): void
    {
        Appointment::factory()->count(12)->create(['user_id' => $this->client->id, 'practitioner_id' => $this->doctor->id, 'scheduled_at' => fn () => now()->addDays(fake()->unique()->numberBetween(2, 200))]);
        Appointment::factory()->create(['practitioner_id' => $this->doctor->id, 'scheduled_at' => now()->addDays(300)]); // someone else's; outside the 2-200 day range so the random slot can't collide

        $response = $this->actingAs($this->client)->get(route('account.appointments.index'))->assertOk();

        $this->assertSame(10, $response->viewData('appointments')->count());
        $this->assertSame(12, $response->viewData('appointments')->total());
    }
}
