<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\AssessmentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\ConsentRecord;
use App\Models\ContactRequest;
use App\Models\Measurement;
use App\Models\MedicalAssessment;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\SideEffectReport;
use App\Models\User;
use App\Models\WeightPlan;
use App\Notifications\GenericNotification;
use App\Services\ClientJourney;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardsAndJourneyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
    }

    private function nextStep(User $client): array
    {
        return app(ClientJourney::class)->nextStep($client);
    }

    // ---- next step: one clear action at every stage ------------------------------------------------------------------------------

    public function test_a_brand_new_client_is_invited_to_the_free_consultation(): void
    {
        $step = $this->nextStep($this->client());

        $this->assertSame('ابدأ باستشارتك المجانية', $step['title']);
        $this->assertSame(route('account.appointments.create', ['type' => 'free_intro']), $step['url']);
    }

    public function test_with_an_upcoming_appointment_that_is_the_next_step(): void
    {
        $client = $this->client();
        $appointment = Appointment::factory()->freeIntro()->create(['user_id' => $client->id, 'scheduled_at' => now()->addDay()]);

        $step = $this->nextStep($client);

        $this->assertSame('موعدك القادم', $step['title']);
        $this->assertSame(route('account.appointments.show', $appointment), $step['url']);
    }

    public function test_after_the_free_consultation_the_client_is_pointed_to_the_packages(): void
    {
        $client = $this->client();
        Appointment::factory()->freeIntro()->status(AppointmentStatus::Completed)->create(['user_id' => $client->id, 'scheduled_at' => now()->subDay()]);

        $step = $this->nextStep($client);

        $this->assertSame('اختر الباقة المناسبة', $step['title']);
        $this->assertSame(route('account.subscription.show'), $step['url']);
    }

    public function test_a_subscriber_without_an_assessment_is_asked_to_complete_it(): void
    {
        $client = $this->client();
        $this->subscribe($client);
        Appointment::factory()->freeIntro()->status(AppointmentStatus::Completed)->create(['user_id' => $client->id, 'scheduled_at' => now()->subDay()]);

        $this->assertSame('أكمل تقييمك الطبي', $this->nextStep($client)['title']);
    }

    public function test_a_submitted_assessment_tells_the_client_it_is_with_the_doctor(): void
    {
        $client = $this->client();
        $this->subscribe($client);
        $this->submittedAssessment($client);

        $this->assertSame('تقييمك عند الطبيب', $this->nextStep($client)['title']);
    }

    public function test_when_the_doctor_needs_more_information_that_outranks_everything_else(): void
    {
        $client = $this->client();
        $this->subscribe($client);
        Appointment::factory()->create(['user_id' => $client->id, 'scheduled_at' => now()->addDay()]);
        MedicalAssessment::factory()->create(['user_id' => $client->id, 'status' => AssessmentStatus::NeedsInfo]);

        $this->assertSame('يحتاج الطبيب إلى معلومات إضافية', $this->nextStep($client)['title']);
    }

    public function test_an_approved_client_waits_for_the_plan_then_is_asked_to_log_measurements(): void
    {
        $client = $this->approvedClient();
        $this->subscribe($client);

        $this->assertSame('فريقك يُعدّ خطتك', $this->nextStep($client)['title']);

        WeightPlan::factory()->create(['user_id' => $client->id]);
        $this->assertSame('سجّل قياساتك', app(ClientJourney::class)->nextStep($client)['title']);
    }

    public function test_a_due_dose_outranks_every_other_step(): void
    {
        $client = $this->approvedClient();
        $this->subscribe($client);
        WeightPlan::factory()->create(['user_id' => $client->id]);
        $rx = Prescription::factory()->due()->create(['user_id' => $client->id]);

        $step = $this->nextStep($client);

        $this->assertSame('لديك جرعة مستحقة', $step['title']);
        $this->assertSame(route('account.medications.show', $rx), $step['url']);
    }

    public function test_the_ten_journey_steps_are_tracked(): void
    {
        $client = $this->approvedClient();
        $this->subscribe($client);
        WeightPlan::factory()->create(['user_id' => $client->id]);
        Prescription::factory()->create(['user_id' => $client->id]);
        Order::factory()->create(['user_id' => $client->id]);
        Appointment::factory()->freeIntro()->status(AppointmentStatus::Completed)->create(['user_id' => $client->id, 'scheduled_at' => now()->subWeek()]);

        $steps = app(ClientJourney::class)->steps($client);

        $this->assertCount(10, $steps);
        $this->assertSame(['discover', 'free_consultation', 'details', 'attend', 'assessment', 'subscribe', 'plan', 'treatment', 'order', 'manage'], array_column($steps, 'key'));
        $this->assertSame(10, count(array_filter($steps, fn ($s) => $s['done'])));

        $newbie = app(ClientJourney::class)->steps($this->client());
        $this->assertSame(['discover', 'details'], array_column(array_filter($newbie, fn ($s) => $s['done']), 'key'));
    }

    // ---- dashboards render with realistic data ------------------------------------------------------------------------------

    public function test_the_client_dashboard_shows_welcome_next_step_subscription_plan_and_emergency_note(): void
    {
        $client = $this->approvedClient(['name' => 'منى العلي'], subscribed: false);
        $this->subscribe($client, ['followups_used' => 1]);
        WeightPlan::factory()->create(['user_id' => $client->id, 'start_weight_kg' => 95, 'target_weight_kg' => 80]);
        Measurement::factory()->create(['user_id' => $client->id, 'weight_kg' => 90]);
        Prescription::factory()->create(['user_id' => $client->id, 'next_dose_at' => now()->addHours(5)]);
        Order::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('أهلًا منى العلي')
            ->assertSee('كل خطوة في المتابعة تساعد فريقك على فهم تقدمك وتقديم دعم أنسب لك.')
            ->assertSee('خطوتك القادمة')
            ->assertSee('33%')
            ->assertSee('استشارات المتابعة المتبقية: 2')
            ->assertSee('رحلتك في اتزان')
            ->assertSee('اتزان ليست خدمة طوارئ.');
    }

    public function test_the_dashboard_progress_bar_exposes_its_value_to_assistive_technology(): void
    {
        $client = $this->client();
        WeightPlan::factory()->create(['user_id' => $client->id, 'start_weight_kg' => 100, 'target_weight_kg' => 80]);
        Measurement::factory()->create(['user_id' => $client->id, 'weight_kg' => 90]);

        $this->actingAs($client)->get(route('account.dashboard'))
            ->assertSee('role="progressbar"', false)->assertSee('aria-valuenow="50"', false)->assertSee('aria-valuemin="0"', false)->assertSee('aria-valuemax="100"', false);
    }

    public function test_journey_steps_carry_a_text_alternative_to_their_colour(): void
    {
        $this->actingAs($this->client())->get(route('account.dashboard'))->assertSee('تمت')->assertSee('قادمة');
    }

    public function test_the_practitioner_dashboards_render(): void
    {
        $doctor = $this->doctor(['accepts_instant' => true]);
        $this->submittedAssessment($this->client());
        MedicalAssessment::factory()->withRedFlag()->create(['user_id' => $this->client()->id]);
        Appointment::factory()->create(['practitioner_id' => $doctor->id, 'scheduled_at' => now()->addHour()]);
        Appointment::factory()->instantRequest()->create(['user_id' => $this->client(['name' => 'عميلة فورية'])->id]);
        ContactRequest::factory()->create(['target' => 'doctor', 'priority' => 'urgent']);

        $this->actingAs($doctor->user)->get(route('practitioner.dashboard'))
            ->assertOk()->assertSee('لوحة الطبيب')->assertSee('عميلة فورية')->assertSee('تحتوي على تنبيهات سلامة');

        $dietitian = $this->dietitian();
        WeightPlan::factory()->create(['dietitian_id' => $dietitian->id, 'user_id' => $this->client(['name' => 'عميلة مسندة'])->id]);
        $this->actingAs($dietitian->user)->get(route('practitioner.dashboard'))->assertOk()->assertSee('عميلة مسندة');
    }

    public function test_an_unverified_practitioner_is_told_why_they_are_not_visible(): void
    {
        $doctor = $this->doctor(['license_verified_at' => null]);

        $this->actingAs($doctor->user)->get(route('practitioner.dashboard'))->assertSee('ترخيصك لم يُوثَّق بعد');
    }

    public function test_care_pharmacy_and_delivery_dashboards_render(): void
    {
        ContactRequest::factory()->create(['priority' => 'urgent']);
        SideEffectReport::factory()->create();

        $this->actingAs($this->staff(Role::CareTeam))->get(route('care.dashboard'))->assertOk()->assertSee('طلبات عاجلة');
        $this->actingAs($this->pharmacist())->get(route('pharmacy.orders.index'))->assertOk();
        $this->actingAs($this->staff(Role::Delivery))->get(route('delivery.orders.index'))->assertOk();
    }

    public function test_the_notification_centre_lists_and_marks_notifications_read_for_every_role(): void
    {
        foreach ([$this->client(), $this->admin(), $this->doctor()->user, $this->staff(Role::CareTeam)] as $user) {
            $user->notify(new GenericNotification('تمت إضافة وصفة جديدة إلى حسابك', 'اطّلع على التفاصيل ومواعيد الجرعات من حسابك.', url('/')));

            $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('تمت إضافة وصفة جديدة')->assertSee('جديد');
            $this->assertSame(1, $user->unreadNotifications()->count());

            $this->post(route('notifications.read'))->assertRedirect();
            $this->assertSame(0, $user->unreadNotifications()->count());
        }
    }

    public function test_the_unread_count_is_shown_in_the_header(): void
    {
        $client = $this->client();
        $client->notify(new GenericNotification('عنوان', 'نص', null));
        $client->notify(new GenericNotification('عنوان ثان', 'نص', null));

        $this->actingAs($client)->get(route('account.dashboard'))->assertSee('إشعارات غير مقروءة');
    }

    public function test_users_only_see_their_own_notifications(): void
    {
        $owner = $this->client();
        $owner->notify(new GenericNotification('إشعار خاص بصاحبه', 'نص', null));

        $this->actingAs($this->client())->get(route('notifications.index'))->assertDontSee('إشعار خاص بصاحبه');
    }

    // ---- profile ------------------------------------------------------------------------------

    public function test_the_client_updates_their_profile_and_language_preference(): void
    {
        $client = $this->client(['name' => 'اسم قديم']);

        $this->actingAs($client)->put(route('account.profile.update'), [
            'name' => 'اسم جديد', 'email' => 'NEW@Example.com', 'city' => 'جدة', 'locale' => 'en', 'gender' => 'female', 'birth_date' => '1990-01-01',
        ])->assertRedirect(route('account.profile.edit'));

        $client->refresh();
        $this->assertSame('اسم جديد', $client->name);
        $this->assertSame('new@example.com', $client->email, 'emails are stored lower-case');
        $this->assertSame('en', $client->locale);
        $this->get(route('account.profile.edit'))->assertSee('lang="en"', false);
    }

    public function test_the_mobile_number_cannot_be_changed_from_the_profile(): void
    {
        $client = $this->client(['phone' => '+966551234567']);

        $this->actingAs($client)->put(route('account.profile.update'), ['name' => 'اسم', 'phone' => '0559999999', 'locale' => 'ar']);

        $this->assertSame('+966551234567', $client->fresh()->phone);
    }

    public function test_a_client_cannot_escalate_their_role_through_the_profile_form(): void
    {
        $client = $this->client();

        $this->actingAs($client)->put(route('account.profile.update'), ['name' => 'اسم', 'locale' => 'ar', 'role' => 'admin', 'is_active' => 0, 'pharmacy_id' => 1]);

        $this->assertSame(Role::Client, $client->fresh()->role);
        $this->assertTrue($client->fresh()->is_active);
    }

    public function test_marketing_consent_changes_are_recorded_only_when_they_change(): void
    {
        $client = $this->client();
        $update = fn (bool $marketing) => $this->actingAs($client)->put(route('account.profile.update'), ['name' => 'اسم كامل', 'locale' => 'ar', 'marketing_consent' => $marketing ? '1' : '0']);

        $update(false);
        $this->assertDatabaseCount('consent_records', 0);

        $update(true);
        $update(true);
        $update(false);

        $this->assertSame([true, false], ConsentRecord::where('type', 'marketing')->orderBy('id')->pluck('granted')->all());
    }

    public function test_profile_validation_and_unique_email(): void
    {
        $this->client(['email' => 'taken@example.com']);
        $client = $this->client();

        $this->actingAs($client)->put(route('account.profile.update'), ['name' => '', 'locale' => 'fr'])->assertSessionHasErrors(['name', 'locale']);
        $this->actingAs($client)->put(route('account.profile.update'), ['name' => 'اسم كامل', 'locale' => 'ar', 'email' => 'taken@example.com'])->assertSessionHasErrors('email');
        $this->actingAs($client)->put(route('account.profile.update'), ['name' => 'اسم كامل', 'locale' => 'ar', 'email' => $client->email])->assertSessionHasNoErrors();
    }

    public function test_changing_the_password_needs_the_current_one_and_a_strong_new_one(): void
    {
        $client = $this->client();

        $this->actingAs($client)->put(route('account.profile.password'), ['current_password' => 'wrong', 'password' => 'NewStr0ngPass1', 'password_confirmation' => 'NewStr0ngPass1'])->assertSessionHasErrors('current_password');
        $this->actingAs($client)->put(route('account.profile.password'), ['current_password' => 'password1', 'password' => 'password1', 'password_confirmation' => 'password1'])->assertSessionHasErrors('password');
        $this->actingAs($client)->put(route('account.profile.password'), ['current_password' => 'password1', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');

        $this->actingAs($client)->put(route('account.profile.password'), ['current_password' => 'password1', 'password' => 'NewStr0ngPass1', 'password_confirmation' => 'NewStr0ngPass1'])->assertSessionHas('status');
        $this->assertTrue(Hash::check('NewStr0ngPass1', $client->fresh()->password));
    }

    // ---- engineering guard-rails ------------------------------------------------------------------------------

    public function test_lazy_loading_is_forbidden_in_tests_so_n_plus_one_bugs_fail_loudly(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        Appointment::factory()->count(2)->create();
        $appointments = Appointment::all();

        $this->expectException(LazyLoadingViolationException::class);

        $appointments->each(fn (Appointment $a) => $a->practitioner);
    }

    public function test_sensitive_user_columns_cannot_be_mass_assigned(): void
    {
        // In strict mode Laravel refuses outright (instead of silently dropping the columns).
        $this->expectException(MassAssignmentException::class);

        new User(['name' => 'x', 'role' => 'admin', 'is_active' => false, 'pharmacy_id' => 5]);
    }

    public function test_the_allowed_user_columns_are_exactly_the_profile_fields(): void
    {
        $this->assertEqualsCanonicalizing(
            ['name', 'phone', 'email', 'password', 'gender', 'birth_date', 'city', 'locale'],
            (new User)->getFillable(),
        );
    }

    public function test_passwords_are_hashed_and_hidden_from_serialisation(): void
    {
        $client = $this->client();

        $this->assertNotSame('password1', $client->password);
        $this->assertStringStartsWith('$2y$', $client->password);
        $this->assertArrayNotHasKey('password', $client->toArray());
        $this->assertArrayNotHasKey('remember_token', $client->toArray());
    }
}
