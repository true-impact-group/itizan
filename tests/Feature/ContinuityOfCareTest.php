<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Practitioner;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\GenericNotification;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Medication needs a subscription, the approving doctor follows the client, loose ends are surfaced. */
class ContinuityOfCareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));
    }

    private function endedSubscription(User $client, int $daysAgo): Subscription
    {
        return Subscription::factory()->expired()->create([
            'user_id' => $client->id, 'starts_at' => now()->subDays($daysAgo + 180), 'ends_at' => now()->subDays($daysAgo),
        ]);
    }

    private function prescribePayload(): array
    {
        return [
            'medication_name' => 'دواء', 'dose' => '1 ملغ', 'interval_days' => '7', 'dose_time' => '09:00', 'starts_on' => today()->toDateString(),
        ];
    }

    // ---- medication needs a subscription ------------------------------------------------------------------------------

    public function test_a_doctor_cannot_prescribe_to_a_client_without_an_active_subscription(): void
    {
        $doctor = $this->doctor();
        $client = $this->approvedClient([], subscribed: false);

        $this->actingAs($doctor->user)->post(route('practitioner.prescriptions.store', $client), $this->prescribePayload())
            ->assertSessionHas('error', 'لا يمكن كتابة وصفة لعميل ليس لديه اشتراك فعّال.');

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_an_expired_subscription_does_not_count(): void
    {
        $client = $this->approvedClient([], subscribed: false);
        $this->endedSubscription($client, 1);

        $this->actingAs($this->doctor()->user)->post(route('practitioner.prescriptions.store', $client), $this->prescribePayload())->assertSessionHas('error');

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_the_prescription_form_explains_why_it_is_unavailable(): void
    {
        $client = $this->approvedClient([], subscribed: false);

        $this->actingAs($this->doctor()->user)->get(route('practitioner.clients.show', $client))
            ->assertOk()->assertSee('لا يمكن كتابة وصفة لعميل ليس لديه اشتراك فعّال.')->assertDontSee('كتابة وصفة جديدة');
    }

    public function test_medication_can_only_be_ordered_with_a_subscription_but_supplements_are_open_to_all(): void
    {
        $client = $this->client();
        $medication = Product::factory()->medication()->create();
        $supplement = Product::factory()->create();
        $rx = Prescription::factory()->create(['user_id' => $client->id, 'product_id' => $medication->id]);
        $order = fn (array $items, array $extra = []) => $this->actingAs($client)->post(route('account.orders.store'), [
            'items' => $items, 'delivery_city' => 'الرياض', 'delivery_address' => 'حي الملقا، شارع الأمير محمد', ...$extra,
        ]);

        $order([['product_id' => $medication->id, 'quantity' => 1]], ['prescription_id' => $rx->id])
            ->assertSessionHas('error', 'طلب الأدوية متاح للمشتركين فقط. جدّد اشتراكك لمتابعة طلب دوائك.');
        $this->assertDatabaseCount('orders', 0);

        $order([['product_id' => $supplement->id, 'quantity' => 1]])->assertRedirect();
        $this->assertDatabaseCount('orders', 1);

        $this->subscribe($client);
        $order([['product_id' => $medication->id, 'quantity' => 1]], ['prescription_id' => $rx->id])->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_the_client_sees_what_to_do_when_the_subscription_has_lapsed(): void
    {
        $client = $this->client();
        $this->endedSubscription($client, 2);
        $rx = Prescription::factory()->create(['user_id' => $client->id]);
        Product::factory()->medication()->create(['name_ar' => 'دواء مخفي بلا اشتراك']);

        $this->actingAs($client)->get(route('account.medications.index'))->assertSee('اشتراكك غير فعّال')->assertSee('جدّد اشتراكك');
        $this->get(route('account.medications.show', $rx))->assertSee('جدّد اشتراكك لطلب الدواء')->assertDontSee('اطلب هذا الدواء');
        $this->get(route('account.orders.create'))->assertSee('طلب الأدوية متاح للمشتركين فقط')->assertDontSee('دواء مخفي بلا اشتراك');
    }

    public function test_dose_tracking_and_reminders_keep_working_after_the_subscription_ends(): void
    {
        Notification::fake();
        $client = $this->client();
        $this->endedSubscription($client, 3);
        $rx = Prescription::factory()->due()->create(['user_id' => $client->id]);

        $this->actingAs($client)->post(route('account.medications.doses.store', $rx), ['status' => 'taken'])->assertSessionHas('status');

        $rx->update(['next_dose_at' => now()->addMinutes(20)]);
        $this->artisan('itizan:dose-reminders')->expectsOutput('Dose reminders queued: 1');
    }

    // ---- lapsed prescriptions ------------------------------------------------------------------------------

    public function test_a_prescription_is_lapsed_only_after_the_grace_period_and_only_without_a_current_subscription(): void
    {
        $inGrace = $this->client();
        $this->endedSubscription($inGrace, 6);
        $lapsed = $this->client();
        $this->endedSubscription($lapsed, 8);
        $renewed = $this->client();
        $this->endedSubscription($renewed, 30);
        $this->subscribe($renewed);
        $twice = $this->client();
        $this->endedSubscription($twice, 40);
        $this->endedSubscription($twice, 2);

        foreach ([$inGrace, $lapsed, $renewed, $twice] as $client) {
            Prescription::factory()->create(['user_id' => $client->id]);
        }
        Prescription::factory()->create(['user_id' => $lapsed->id, 'status' => PrescriptionStatus::Stopped, 'next_dose_at' => null]);

        $ids = Prescription::query()->lapsed()->pluck('user_id')->all();

        $this->assertSame([$lapsed->id], $ids, 'only the client whose LAST subscription ended more than 7 days ago');
    }

    public function test_the_command_informs_everyone_once_and_never_stops_the_prescription(): void
    {
        Notification::fake();
        $care = $this->staff(Role::CareTeam);
        $doctor = $this->doctor();
        $client = $this->client();
        $this->endedSubscription($client, 9);
        $rx = Prescription::factory()->create(['user_id' => $client->id, 'practitioner_id' => $doctor->id]);

        $this->artisan('itizan:lapsed-prescriptions')->expectsOutput('Lapsed prescriptions flagged: 1');
        $this->artisan('itizan:lapsed-prescriptions')->expectsOutput('Lapsed prescriptions flagged: 0');

        Notification::assertSentToTimes($client, GenericNotification::class, 1);
        Notification::assertSentToTimes($doctor->user, GenericNotification::class, 1);
        Notification::assertSentToTimes($care, GenericNotification::class, 1);
        $this->assertSame(PrescriptionStatus::Active, $rx->fresh()->status, 'stopping treatment is the doctor\'s decision');
        $this->assertNotNull($rx->fresh()->lapse_notified_at);
    }

    public function test_a_second_lapse_after_a_renewal_is_notified_again_and_the_text_is_generic(): void
    {
        $client = $this->client();
        $this->endedSubscription($client, 9);
        $rx = Prescription::factory()->create(['user_id' => $client->id, 'medication_name' => 'اسم دواء سري']);
        $this->artisan('itizan:lapsed-prescriptions');
        $first = $rx->fresh()->lapse_notified_at;

        // Renewed for a while, then lapsed again.
        $this->travel(40)->days();
        $this->endedSubscription($client, 10);
        Notification::fake();
        $this->artisan('itizan:lapsed-prescriptions')->expectsOutput('Lapsed prescriptions flagged: 1');

        Notification::assertSentTo($client, GenericNotification::class, fn (GenericNotification $n) => ! str_contains(json_encode($n->toArray($client), JSON_UNESCAPED_UNICODE), 'اسم دواء سري'));
        $this->assertTrue($rx->fresh()->lapse_notified_at->gt($first));
    }

    public function test_lapsed_prescriptions_show_on_the_doctors_dashboard_with_the_decision_left_to_them(): void
    {
        $doctor = $this->doctor();
        $client = $this->client(['name' => 'عميل اشتراكه منتهٍ']);
        $this->endedSubscription($client, 9);
        Prescription::factory()->create(['user_id' => $client->id, 'practitioner_id' => $doctor->id]);

        $this->actingAs($doctor->user)->get(route('practitioner.dashboard'))
            ->assertSee('وصفات تحتاج مراجعة بعد انتهاء الاشتراك')->assertSee('عميل اشتراكه منتهٍ')->assertSee('القرار لك');

        $this->subscribe($client);
        $this->get(route('practitioner.dashboard'))->assertDontSee('عميل اشتراكه منتهٍ');
    }

    // ---- primary doctor ------------------------------------------------------------------------------

    public function test_the_doctor_who_approves_the_assessment_becomes_the_primary_doctor_and_it_is_not_overwritten(): void
    {
        $first = $this->doctor();
        $second = $this->doctor();
        $client = $this->client();
        $this->subscribe($client);

        $this->actingAs($first->user)->post(route('practitioner.assessments.review', $this->submittedAssessment($client)), ['decision' => 'approved']);
        $this->assertSame($first->id, $client->fresh()->primary_practitioner_id);

        $reassessment = $this->submittedAssessment($client);
        $this->actingAs($second->user)->post(route('practitioner.assessments.review', $reassessment), ['decision' => 'approved']);
        $this->assertSame($first->id, $client->fresh()->primary_practitioner_id, 'the existing choice is kept');
    }

    public function test_needing_information_or_declining_does_not_assign_a_doctor(): void
    {
        $doctor = $this->doctor();
        $client = $this->client();

        $this->actingAs($doctor->user)->post(route('practitioner.assessments.review', $this->submittedAssessment($client)), ['decision' => 'needs_info', 'notes' => 'أضف تحليلًا']);

        $this->assertNull($client->fresh()->primary_practitioner_id);
    }

    public function test_booking_offers_the_primary_doctor_first_with_a_switch_to_any_doctor(): void
    {
        $primary = Practitioner::factory()->create();
        $primary->availabilities()->create(['weekday' => now()->addDay()->dayOfWeek, 'start_time' => '09:00', 'end_time' => '12:00', 'slot_minutes' => 20]);
        $this->doctor(); // another doctor available all day
        $client = $this->client();
        $client->forceFill(['primary_practitioner_id' => $primary->id])->save();
        $this->subscribe($client);
        $date = now()->addDay()->toDateString();

        $mine = $this->actingAs($client)->get(route('account.appointments.create', ['type' => 'follow_up', 'date' => $date]))
            ->assertOk()->assertSee('طبيبك المتابع')->assertSee($primary->user->name)->assertSee('عرض مواعيد أي طبيب متاح');
        $this->assertSame(9, substr_count($mine->getContent(), 'name="starts_at"'), '09:00-12:00 in 20-minute slots');

        $any = $this->get(route('account.appointments.create', ['type' => 'follow_up', 'date' => $date, 'with' => 'any']))->assertSee('العودة إلى مواعيد طبيبي');
        $this->assertSame(72, substr_count($any->getContent(), 'name="starts_at"'));
    }

    public function test_a_booking_made_from_the_primary_view_goes_to_the_primary_doctor_even_if_others_are_free(): void
    {
        $primary = $this->doctor();
        $this->doctor();
        $client = $this->client();
        $client->forceFill(['primary_practitioner_id' => $primary->id])->save();
        $this->subscribe($client);

        $this->actingAs($client)->post(route('account.appointments.store'), [
            'type' => 'follow_up', 'mode' => 'video', 'starts_at' => now()->addDay()->format('Y-m-d').' 10:00', 'practitioner_id' => $primary->id, 'acknowledge' => '1',
        ])->assertRedirect();

        $this->assertSame($primary->id, Appointment::firstOrFail()->practitioner_id);
    }

    public function test_when_the_primary_doctor_is_busy_the_booking_is_refused_not_silently_moved(): void
    {
        $primary = $this->doctor();
        $this->doctor();
        $client = $this->client();
        $this->subscribe($client);
        $slot = now()->addDay()->format('Y-m-d').' 10:00';
        Appointment::factory()->create(['practitioner_id' => $primary->id, 'scheduled_at' => Carbon::parse($slot)]);

        $this->actingAs($client)->post(route('account.appointments.store'), [
            'type' => 'follow_up', 'mode' => 'video', 'starts_at' => $slot, 'practitioner_id' => $primary->id, 'acknowledge' => '1',
        ])->assertSessionHas('error', 'هذا الموعد لم يعد متاحًا، اختر موعدًا آخر.');
    }

    public function test_without_a_primary_doctor_or_with_an_unverified_one_everyone_is_offered(): void
    {
        $this->doctor();
        $client = $this->client();
        $this->actingAs($client)->get(route('account.appointments.create', ['type' => 'free_intro']))->assertDontSee('طبيبك المتابع');

        $unverified = Practitioner::factory()->unverified()->create();
        $client->forceFill(['primary_practitioner_id' => $unverified->id])->save();
        $this->get(route('account.appointments.create', ['type' => 'free_intro']))->assertDontSee('طبيبك المتابع');
    }

    public function test_the_admin_can_reassign_or_clear_the_primary_doctor_and_it_is_audited(): void
    {
        $old = $this->doctor();
        $new = $this->doctor();
        $client = $this->client();
        $client->forceFill(['primary_practitioner_id' => $old->id])->save();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.clients.show', $client))->assertSee('الطبيب المتابع')->assertSee('تغيير الطبيب المتابع');

        $this->put(route('admin.clients.primary-doctor', $client), ['practitioner_id' => $new->id])->assertSessionHas('status');
        $this->assertSame($new->id, $client->fresh()->primary_practitioner_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.primary_doctor_changed', 'user_id' => $admin->id]);

        $this->put(route('admin.clients.primary-doctor', $client), ['practitioner_id' => ''])->assertSessionHas('status');
        $this->assertNull($client->fresh()->primary_practitioner_id);
    }

    public function test_only_a_verified_doctor_can_be_assigned_and_only_by_an_admin(): void
    {
        $client = $this->client();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.clients.primary-doctor', $client), ['practitioner_id' => $this->dietitian()->id])->assertSessionHasErrors('practitioner_id');
        $this->actingAs($admin)->put(route('admin.clients.primary-doctor', $client), ['practitioner_id' => Practitioner::factory()->unverified()->create()->id])->assertStatus(422);

        $support = $this->staff(Role::Support);
        $this->actingAs($support)->put(route('admin.clients.primary-doctor', $client), ['practitioner_id' => $this->doctor()->id])->assertForbidden();
        $this->actingAs($support)->get(route('admin.clients.show', $client))->assertDontSee('تغيير الطبيب المتابع');

        $this->assertNull($client->fresh()->primary_practitioner_id);
    }

    public function test_the_doctor_dashboard_lists_their_own_clients_and_other_doctors_can_still_open_the_file(): void
    {
        $mine = $this->doctor();
        $other = $this->doctor();
        $client = $this->approvedClient(['name' => 'عميلتي الأساسية']);
        $client->forceFill(['primary_practitioner_id' => $mine->id])->save();

        $this->actingAs($mine->user)->get(route('practitioner.dashboard'))->assertSee('عملائي')->assertSee('عميلتي الأساسية');
        $this->actingAs($other->user)->get(route('practitioner.dashboard'))->assertDontSee('عميلتي الأساسية');
        $this->actingAs($other->user)->get(route('practitioner.clients.show', $client))->assertOk();
    }

    // ---- email at checkout ------------------------------------------------------------------------------

    public function test_a_client_without_an_email_must_add_one_to_subscribe(): void
    {
        $this->seedCatalog();
        $package = Package::where('slug', 'six-months')->firstOrFail();
        $client = User::factory()->withoutEmail()->create();
        $post = fn (array $extra = []) => $this->actingAs($client)->post(route('account.checkout.store', $package), ['payment_method' => 'mada', 'accept_terms' => '1', ...$extra]);

        $this->actingAs($client)->get(route('account.checkout.show', $package))->assertSee('name="email"', false)->assertSee('نحتاجه لإرسال الإيصال');

        $post()->assertSessionHasErrors('email');
        $post(['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('payments', 0);

        $post(['email' => 'New.Client@Example.com'])->assertRedirect();
        $this->assertSame('new.client@example.com', $client->fresh()->email);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_the_email_must_be_unique_and_is_not_asked_again_once_known(): void
    {
        $this->seedCatalog();
        $package = Package::where('slug', 'six-months')->firstOrFail();
        $this->client(['email' => 'taken@example.com']);
        $noEmail = User::factory()->withoutEmail()->create();
        $withEmail = $this->client(['email' => 'mine@example.com']);

        $this->actingAs($noEmail)->post(route('account.checkout.store', $package), ['payment_method' => 'mada', 'accept_terms' => '1', 'email' => 'taken@example.com'])->assertSessionHasErrors('email');

        $this->actingAs($withEmail)->get(route('account.checkout.show', $package))->assertDontSee('name="email"', false);
        $this->actingAs($withEmail)->post(route('account.checkout.store', $package), ['payment_method' => 'mada', 'accept_terms' => '1'])->assertRedirect();
        $this->assertSame('mine@example.com', $withEmail->fresh()->email);
        $this->assertSame(1, Payment::count());
    }

    // ---- finished sessions left open ------------------------------------------------------------------------------

    public function test_only_confirmed_sessions_that_ended_more_than_15_minutes_ago_await_closure(): void
    {
        $doctor = $this->doctor();
        $make = fn (array $a) => Appointment::factory()->create(['practitioner_id' => $doctor->id, ...$a]);
        $stale = $make(['scheduled_at' => now()->subHour()]);
        $make(['scheduled_at' => now()->subMinutes(25)]);                                    // ended 5 minutes ago
        $make(['scheduled_at' => now()->addHour()]);                                         // future
        $make(['scheduled_at' => now()->subDay(), 'status' => AppointmentStatus::Completed]);
        $make(['scheduled_at' => now()->subDay()->subHour(), 'status' => AppointmentStatus::Cancelled]);

        $this->assertEquals([$stale->id], Appointment::awaitingClosure()->pluck('id')->all());
    }

    public function test_the_doctor_is_reminded_once_and_the_dashboard_lists_the_open_session_until_closed(): void
    {
        Notification::fake();
        $doctor = $this->doctor();
        $client = $this->client(['name' => 'عميل جلسته مفتوحة']);
        $appointment = Appointment::factory()->freeIntro()->create(['user_id' => $client->id, 'practitioner_id' => $doctor->id, 'scheduled_at' => now()->subHours(2)]);
        Appointment::factory()->instantRequest()->create(['scheduled_at' => now()->subHours(3)]);

        $this->artisan('itizan:stale-appointments')->expectsOutput('Closure reminders queued: 1');
        $this->artisan('itizan:stale-appointments')->expectsOutput('Closure reminders queued: 0');
        Notification::assertSentToTimes($doctor->user, GenericNotification::class, 1);

        $this->actingAs($doctor->user)->get(route('practitioner.dashboard'))->assertSee('مواعيد انتهت ولم تُغلق')->assertSee('عميل جلسته مفتوحة');

        $this->post(route('practitioner.appointments.complete', $appointment), ['outcome' => 'completed']);
        $this->get(route('practitioner.dashboard'))->assertDontSee('عميل جلسته مفتوحة');
    }

    public function test_the_new_commands_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events());

        foreach (['itizan:lapsed-prescriptions', 'itizan:stale-appointments'] as $command) {
            $event = $events->first(fn ($e) => str_contains((string) $e->command, $command));
            $this->assertNotNull($event, "{$command} is scheduled");
            $this->assertTrue($event->withoutOverlapping && $event->onOneServer);
        }
    }
}
