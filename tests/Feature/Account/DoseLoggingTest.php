<?php

namespace Tests\Feature\Account;

use App\Enums\ContactRequestStatus;
use App\Enums\ContactTarget;
use App\Enums\DoseStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\Role;
use App\Models\ContactRequest;
use App\Models\DoseLog;
use App\Models\Prescription;
use App\Models\User;
use App\Notifications\DoseReminderNotification;
use App\Notifications\GenericNotification;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DoseLoggingTest extends TestCase
{
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
        $this->client = $this->client();
    }

    private function log(Prescription $rx, array $data)
    {
        return $this->actingAs($this->client)->post(route('account.medications.doses.store', $rx), $data);
    }

    public function test_the_client_records_a_taken_dose_and_the_next_one_is_scheduled_by_the_doctors_interval(): void
    {
        $rx = Prescription::factory()->create(['user_id' => $this->client->id, 'interval_days' => 7, 'next_dose_at' => now()->subHour()]);
        $scheduled = $rx->next_dose_at->copy();

        $this->log($rx, ['status' => 'taken', 'note' => 'بدون مشاكل'])->assertRedirect(route('account.medications.show', $rx));

        $log = DoseLog::firstOrFail();
        $this->assertSame(DoseStatus::Taken, $log->status);
        $this->assertTrue($log->scheduled_for->equalTo($scheduled));
        $this->assertSame($this->client->id, $log->user_id);
        $this->assertTrue($rx->fresh()->next_dose_at->equalTo($scheduled->addDays(7)));
    }

    public function test_a_missed_dose_can_be_recorded_without_blame_and_moves_the_schedule_on(): void
    {
        $rx = Prescription::factory()->due()->create(['user_id' => $this->client->id, 'interval_days' => 1]);

        $this->log($rx, ['status' => 'missed'])->assertSessionHas('status', 'تم تسجيل الجرعة.');

        $this->assertSame(DoseStatus::Missed, DoseLog::firstOrFail()->status);
        $this->assertTrue($rx->fresh()->next_dose_at->isFuture());
    }

    public function test_a_dose_can_be_recorded_up_to_two_hours_early_but_not_earlier(): void
    {
        $rx = Prescription::factory()->create(['user_id' => $this->client->id, 'next_dose_at' => now()->addHours(3)]);

        $this->log($rx, ['status' => 'taken'])->assertSessionHas('error', 'لم يحن موعد الجرعة بعد.');
        $this->assertDatabaseCount('dose_logs', 0);

        $rx->update(['next_dose_at' => now()->addHour()]);
        $this->log($rx, ['status' => 'taken'])->assertSessionHas('status');
        $this->assertDatabaseCount('dose_logs', 1);
    }

    public function test_a_dose_cannot_be_logged_twice(): void
    {
        $rx = Prescription::factory()->due()->create(['user_id' => $this->client->id, 'interval_days' => 7]);

        $this->log($rx, ['status' => 'taken']);
        $this->log($rx, ['status' => 'taken'])->assertSessionHas('error');

        $this->assertDatabaseCount('dose_logs', 1);
    }

    public function test_the_database_refuses_a_duplicate_log_for_the_same_scheduled_dose(): void
    {
        $rx = Prescription::factory()->create(['user_id' => $this->client->id]);
        $at = now()->subDay();
        DoseLog::create(['prescription_id' => $rx->id, 'user_id' => $this->client->id, 'scheduled_for' => $at, 'status' => 'taken', 'logged_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);

        DoseLog::create(['prescription_id' => $rx->id, 'user_id' => $this->client->id, 'scheduled_for' => $at, 'status' => 'missed', 'logged_at' => now()]);
    }

    public function test_the_prescription_completes_after_its_last_dose(): void
    {
        $rx = Prescription::factory()->create([
            'user_id' => $this->client->id, 'interval_days' => 7, 'next_dose_at' => now()->subHour(), 'ends_on' => today()->addDays(3),
        ]);

        $this->log($rx, ['status' => 'taken']);

        $rx->refresh();
        $this->assertSame(PrescriptionStatus::Completed, $rx->status);
        $this->assertNull($rx->next_dose_at);
    }

    public function test_a_stopped_prescription_cannot_be_logged(): void
    {
        $rx = Prescription::factory()->create(['user_id' => $this->client->id, 'status' => PrescriptionStatus::Stopped, 'next_dose_at' => null]);

        $this->log($rx, ['status' => 'taken'])->assertForbidden();
    }

    public function test_status_must_be_taken_or_missed(): void
    {
        $rx = Prescription::factory()->due()->create(['user_id' => $this->client->id]);

        $this->log($rx, ['status' => 'doubled'])->assertSessionHasErrors('status');
        $this->log($rx, [])->assertSessionHasErrors('status');
    }

    // ---- pre-dose questionnaire ------------------------------------------------------------------------------

    public function test_the_pre_dose_questions_are_shown_on_the_prescription_page(): void
    {
        $rx = Prescription::factory()->due()->withQuestion('هل تشعر بدوار شديد؟')->create(['user_id' => $this->client->id]);

        $this->actingAs($this->client)->get(route('account.medications.show', $rx))
            ->assertOk()
            ->assertSee('استبيان قصير قبل الجرعة')
            ->assertSee('هل تشعر بدوار شديد؟')
            ->assertSee('لا يمكنك تعديل الجرعة بنفسك');
    }

    public function test_a_taken_dose_needs_the_pre_dose_answers_but_a_missed_one_does_not(): void
    {
        $rx = Prescription::factory()->due()->withQuestion()->create(['user_id' => $this->client->id]);

        $this->log($rx, ['status' => 'taken'])->assertSessionHasErrors('answers');
        $this->log($rx, ['status' => 'missed'])->assertSessionHasNoErrors();
    }

    public function test_safe_pre_dose_answers_are_stored_encrypted_with_the_log(): void
    {
        $rx = Prescription::factory()->due()->withQuestion()->create(['user_id' => $this->client->id]);

        $this->log($rx, ['status' => 'taken', 'answers' => ['no']]);

        $this->assertSame(['no'], DoseLog::firstOrFail()->pre_dose_answers);
        $this->assertStringStartsWith('eyJpdiI6', (string) DB::table('dose_logs')->value('pre_dose_answers'));
    }

    public function test_an_alarming_pre_dose_answer_holds_the_dose_and_raises_an_urgent_request_to_the_doctor(): void
    {
        Notification::fake();
        $care = $this->staff(Role::CareTeam);
        $rx = Prescription::factory()->due()->withQuestion()->create(['user_id' => $this->client->id]);
        $scheduled = $rx->next_dose_at->copy();

        $this->log($rx, ['status' => 'taken', 'answers' => ['yes']])
            ->assertSessionHas('warning')
            ->assertRedirect(route('account.medications.show', $rx));

        $this->assertDatabaseCount('dose_logs', 0);
        $this->assertTrue($rx->fresh()->next_dose_at->equalTo($scheduled), 'the dose stays due, it is not silently skipped');

        $request = ContactRequest::firstOrFail();
        $this->assertSame(ContactTarget::Doctor, $request->target);
        $this->assertSame('urgent', $request->priority);
        $this->assertSame('pre_dose', $request->source);
        $this->assertSame(ContactRequestStatus::Open, $request->status);
        $this->assertSame($this->client->id, $request->user_id);
        Notification::assertSentTo($care, GenericNotification::class);
    }

    public function test_the_alert_direction_follows_what_the_doctor_configured(): void
    {
        // The doctor wants to be alerted when the client answers "no" to "Did you eat today?".
        $rx = Prescription::factory()->due()->withQuestion('هل تناولت وجبة اليوم؟', 'no')->create(['user_id' => $this->client->id]);

        $this->log($rx, ['status' => 'taken', 'answers' => ['yes']]);
        $this->assertDatabaseCount('dose_logs', 1);
    }

    public function test_the_alert_direction_no_holds_the_dose(): void
    {
        $rx = Prescription::factory()->due()->withQuestion('هل تناولت وجبة اليوم؟', 'no')->create(['user_id' => $this->client->id]);

        $this->log($rx, ['status' => 'taken', 'answers' => ['no']])->assertSessionHas('warning');
        $this->assertDatabaseCount('dose_logs', 0);
    }

    public function test_the_urgent_request_is_visible_to_doctors_and_handled_from_their_queue(): void
    {
        $rx = Prescription::factory()->due()->withQuestion()->create(['user_id' => $this->client->id]);
        $this->log($rx, ['status' => 'taken', 'answers' => ['yes']]);
        $doctor = $this->doctor();

        $this->actingAs($doctor->user)->get(route('care.contact-requests.index'))->assertOk()->assertSee('عاجل')->assertSee($this->client->name);
    }

    // ---- reminders never leak medical detail ------------------------------------------------------------------------------

    public function test_a_dose_reminder_says_nothing_about_which_medication_or_dose(): void
    {
        $rx = Prescription::factory()->create(['user_id' => $this->client, 'medication_name' => 'اسم دواء سري', 'dose' => '7.77 ملغ']);

        $notification = new DoseReminderNotification($rx);
        $payload = json_encode($notification->toArray($this->client), JSON_UNESCAPED_UNICODE);
        $mail = json_encode($notification->toMail($this->client)->toArray(), JSON_UNESCAPED_UNICODE);

        foreach ([$payload, $mail] as $text) {
            $this->assertStringNotContainsString('اسم دواء سري', $text);
            $this->assertStringNotContainsString('7.77', $text);
        }
        $this->assertStringContainsString('حان موعد جرعتك', $payload);
    }

    public function test_client_cannot_log_a_dose_on_someone_elses_prescription(): void
    {
        $rx = Prescription::factory()->due()->create();

        $this->log($rx, ['status' => 'taken'])->assertForbidden();
        $this->assertDatabaseCount('dose_logs', 0);
    }

    public function test_medication_list_and_details_render_with_several_prescriptions(): void
    {
        Prescription::factory()->count(3)->create(['user_id' => $this->client->id]);
        Prescription::factory()->create(['user_id' => $this->client->id, 'status' => PrescriptionStatus::Stopped]);

        $this->actingAs($this->client)->get(route('account.medications.index'))->assertOk()->assertSee('الدواء والجرعة يحددهما الطبيب وحده');
    }
}
