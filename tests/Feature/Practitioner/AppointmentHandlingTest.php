<?php

namespace Tests\Feature\Practitioner;

use App\Actions\Appointments\CompleteAppointment;
use App\Enums\AppointmentStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Appointment;
use App\Models\Package;
use App\Models\Practitioner;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppointmentHandlingTest extends TestCase
{
    private Practitioner $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doctor = $this->doctor();
    }

    private function appointment(array $attributes = []): Appointment
    {
        return Appointment::factory()->create(['practitioner_id' => $this->doctor->id, ...$attributes]);
    }

    public function test_the_doctor_sees_upcoming_and_past_appointments_on_separate_tabs(): void
    {
        $this->appointment(['user_id' => $this->client(['name' => 'عميل قادم'])->id, 'scheduled_at' => now()->addDay()]);
        $this->appointment(['user_id' => $this->client(['name' => 'عميل سابق'])->id, 'scheduled_at' => now()->subDay(), 'status' => AppointmentStatus::Completed]);
        Appointment::factory()->create(['user_id' => $this->client(['name' => 'عميل طبيب آخر'])->id]);

        $this->actingAs($this->doctor->user)->get(route('practitioner.appointments.index'))
            ->assertOk()->assertSee('عميل قادم')->assertDontSee('عميل سابق')->assertDontSee('عميل طبيب آخر');

        $this->actingAs($this->doctor->user)->get(route('practitioner.appointments.index', ['tab' => 'past']))
            ->assertOk()->assertSee('عميل سابق')->assertDontSee('عميل قادم');
    }

    public function test_opening_an_appointment_shows_the_client_the_session_link_and_is_audited(): void
    {
        $appointment = $this->appointment(['client_notes' => 'أريد مناقشة الخطة']);

        $this->actingAs($this->doctor->user)->get(route('practitioner.appointments.show', $appointment))
            ->assertOk()
            ->assertSee($appointment->user->name)
            ->assertSee('أريد مناقشة الخطة')
            ->assertSee($appointment->meeting_url)
            ->assertSee('بدء الجلسة عبر FTMS');

        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.viewed', 'user_id' => $this->doctor->user_id, 'auditable_id' => $appointment->id]);
    }

    public function test_the_doctor_completes_a_session_with_private_notes(): void
    {
        $appointment = $this->appointment();

        $this->actingAs($this->doctor->user)->post(route('practitioner.appointments.complete', $appointment), ['outcome' => 'completed', 'notes' => 'ملاحظات جلسة سرية'])
            ->assertRedirect(route('practitioner.appointments.show', $appointment));

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Completed, $appointment->status);
        $this->assertNotNull($appointment->completed_at);
        $this->assertSame('ملاحظات جلسة سرية', $appointment->practitioner_notes);
        $this->assertStringNotContainsString('سرية', (string) DB::table('appointments')->value('practitioner_notes'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.completed']);
    }

    public function test_a_session_can_be_marked_no_show(): void
    {
        $appointment = $this->appointment();

        $this->actingAs($this->doctor->user)->post(route('practitioner.appointments.complete', $appointment), ['outcome' => 'no_show']);

        $this->assertSame(AppointmentStatus::NoShow, $appointment->fresh()->status);
    }

    public function test_the_outcome_must_be_completed_or_no_show(): void
    {
        $appointment = $this->appointment();

        $this->actingAs($this->doctor->user)->post(route('practitioner.appointments.complete', $appointment), ['outcome' => 'cancelled'])->assertSessionHasErrors('outcome');
        $this->actingAs($this->doctor->user)->post(route('practitioner.appointments.complete', $appointment), [])->assertSessionHasErrors('outcome');
    }

    public function test_only_the_assigned_doctor_can_complete_and_only_a_confirmed_appointment(): void
    {
        $appointment = $this->appointment();
        $other = $this->doctor();

        $this->actingAs($other->user)->post(route('practitioner.appointments.complete', $appointment), ['outcome' => 'completed'])->assertForbidden();

        $done = $this->appointment(['status' => AppointmentStatus::Completed]);
        $this->actingAs($this->doctor->user)->post(route('practitioner.appointments.complete', $done), ['outcome' => 'completed'])->assertForbidden();
    }

    public function test_the_complete_action_itself_refuses_invalid_states_and_outcomes(): void
    {
        $action = app(CompleteAppointment::class);

        $cancelled = $this->appointment(['status' => AppointmentStatus::Cancelled]);
        try {
            $action->handle($cancelled, AppointmentStatus::Completed);
            $this->fail('expected a business rule violation');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('لا يمكن إنهاء هذا الموعد في حالته الحالية.', $e->getMessage());
        }

        $this->expectException(BusinessRuleViolation::class);
        $action->handle($this->appointment(), AppointmentStatus::Cancelled);
    }

    public function test_another_doctor_cannot_open_a_confirmed_appointment_that_is_not_theirs(): void
    {
        $this->actingAs($this->doctor()->user)->get(route('practitioner.appointments.show', $this->appointment()))->assertForbidden();
    }

    public function test_the_care_team_and_admin_see_appointments_in_the_overview_but_no_session_notes(): void
    {
        $appointment = $this->appointment(['practitioner_notes' => 'ملاحظات سرية للطبيب فقط']);

        $this->actingAs($this->admin())->get(route('admin.appointments.index'))->assertOk()->assertSee($appointment->user->name)->assertDontSee('ملاحظات سرية');
        $this->actingAs($this->admin())->get(route('admin.appointments.index', ['status' => 'cancelled']))->assertDontSee($appointment->user->name);
    }

    public function test_admin_and_support_can_cancel_an_appointment_and_a_follow_up_is_returned(): void
    {
        $client = $this->client();
        $subscription = $this->subscribe($client, ['followups_used' => 1]);
        $appointment = $this->appointment(['user_id' => $client->id, 'type' => 'follow_up', 'subscription_id' => $subscription->id]);

        $this->actingAs($this->staff(Role::Support))->post(route('admin.appointments.cancel', $appointment), ['reason' => 'طلب العميل'])->assertSessionHas('status');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame(0, $subscription->fresh()->followups_used);
    }

    public function test_cancelling_a_finished_appointment_from_the_admin_screen_is_refused(): void
    {
        $appointment = $this->appointment(['status' => AppointmentStatus::Completed]);

        $this->actingAs($this->admin())->post(route('admin.appointments.cancel', $appointment))->assertSessionHas('error');
    }

    public function test_plan_and_staff_forms_render_for_the_right_people(): void
    {
        $client = $this->approvedClient();
        $this->actingAs($this->doctor()->user)->get(route('practitioner.plans.create', $client))->assertOk()->assertSee('إعداد خطة إدارة الوزن')->assertSee('الخطوات المطلوبة من العميل');

        $admin = $this->admin();
        $member = $this->staff(Role::CareTeam);
        $this->actingAs($admin)->get(route('admin.staff.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.staff.edit', $member))->assertOk()->assertSee($member->name);
        $this->actingAs($admin)->get(route('admin.packages.edit', Package::factory()->create()))->assertOk();
        $this->actingAs($admin)->get(route('admin.practitioners.edit', $this->doctor))->assertOk();
    }
}
