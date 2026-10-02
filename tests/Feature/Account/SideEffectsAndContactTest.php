<?php

namespace Tests\Feature\Account;

use App\Enums\ContactRequestStatus;
use App\Enums\ContactTarget;
use App\Enums\Role;
use App\Enums\Severity;
use App\Enums\SideEffectStatus;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\ContactRequest;
use App\Models\Prescription;
use App\Models\SideEffectReport;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SideEffectsAndContactTest extends TestCase
{
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->client = $this->client();
    }

    private function report(array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->client)->post(route('account.side-effects.store'), [
            'symptom' => 'غثيان بعد الجرعة',
            'severity' => 'mild',
            'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
            ...$data,
        ]);
    }

    public function test_a_mild_side_effect_is_recorded_for_the_care_team_without_escalation(): void
    {
        $this->report()->assertRedirect(route('account.side-effects.index'));

        $report = SideEffectReport::firstOrFail();
        $this->assertSame(Severity::Mild, $report->severity);
        $this->assertSame(SideEffectStatus::New, $report->status);
        $this->assertDatabaseCount('contact_requests', 0);
    }

    public function test_a_severe_side_effect_escalates_to_the_doctor_alerts_the_care_team_and_shows_emergency_guidance(): void
    {
        Notification::fake();
        $care = $this->staff(Role::CareTeam);

        $response = $this->report(['severity' => 'severe', 'symptom' => 'ألم شديد في البطن وقيء متكرر']);

        $response->assertSessionHas('warning', fn ($message) => str_contains($message, '997'));
        $this->assertSame(SideEffectStatus::Escalated, SideEffectReport::firstOrFail()->status);

        $request = ContactRequest::firstOrFail();
        $this->assertSame(ContactTarget::Doctor, $request->target);
        $this->assertSame('urgent', $request->priority);
        $this->assertSame('side_effect', $request->source);
        Notification::assertSentTo($care, GenericNotification::class);
    }

    public function test_symptom_text_is_encrypted_and_never_put_in_the_urgent_request_or_notification(): void
    {
        Notification::fake();
        $this->staff(Role::CareTeam);
        $this->report(['severity' => 'severe', 'symptom' => 'وصف سري للعرض الجانبي']);

        $this->assertStringStartsWith('eyJpdiI6', (string) DB::table('side_effect_reports')->value('symptom'));
        $this->assertStringNotContainsString('وصف سري', ContactRequest::firstOrFail()->reason);

        Notification::assertSentTo(User::where('role', 'care_team')->first(), GenericNotification::class,
            fn (GenericNotification $n, $channels) => ! str_contains(json_encode($n->toArray(new User), JSON_UNESCAPED_UNICODE), 'وصف سري'));
    }

    public function test_side_effect_input_is_validated(): void
    {
        $this->report(['symptom' => ''])->assertSessionHasErrors('symptom');
        $this->report(['severity' => 'catastrophic'])->assertSessionHasErrors('severity');
        $this->report(['occurred_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('occurred_at');
        $this->report(['files' => [UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream')]])->assertSessionHasErrors('files.0');
        $this->report(['files' => [UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')]])->assertSessionHasErrors('files.0');
        $this->report(['files' => array_fill(0, 4, UploadedFile::fake()->image('a.jpg'))])->assertSessionHasErrors('files');

        $this->assertDatabaseCount('side_effect_reports', 0);
    }

    public function test_a_report_cannot_be_linked_to_someone_elses_prescription(): void
    {
        $foreign = Prescription::factory()->create();

        $this->report(['prescription_id' => $foreign->id])->assertSessionHas('error', 'الوصفة المختارة غير صحيحة.');

        $this->assertDatabaseCount('side_effect_reports', 0);
    }

    public function test_photos_are_stored_privately_under_a_random_name_and_not_publicly_reachable(): void
    {
        $this->report(['files' => [UploadedFile::fake()->image('rash.jpg')]]);

        $attachment = Attachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringNotContainsString('rash', $attachment->path);
        $this->assertSame('rash.jpg', $attachment->original_name);
        $this->assertSame($this->client->id, $attachment->user_id);

        $this->get('/storage/'.$attachment->path)->assertNotFound();
    }

    public function test_attachment_access_is_limited_to_the_owner_doctors_and_the_care_team(): void
    {
        $this->report(['files' => [UploadedFile::fake()->create('result.pdf', 50, 'application/pdf')]]);
        $attachment = Attachment::firstOrFail();

        $this->actingAs($this->client)->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs($this->doctor()->user)->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs($this->staff(Role::CareTeam))->get(route('attachments.show', $attachment))->assertOk();

        foreach ([$this->client(), $this->admin(), $this->staff(Role::Support), $this->dietitian()->user, $this->pharmacist(), $this->staff(Role::Delivery)] as $outsider) {
            $this->actingAs($outsider)->get(route('attachments.show', $attachment))->assertForbidden();
        }
        $this->get(route('attachments.show', $attachment))->assertForbidden();
    }

    public function test_attachments_require_sign_in(): void
    {
        $this->report(['files' => [UploadedFile::fake()->create('result.pdf', 50, 'application/pdf')]]);
        auth()->logout();

        $this->get(route('attachments.show', Attachment::firstOrFail()))->assertRedirect(route('login'));
    }

    public function test_staff_opening_a_clients_file_is_audited_but_the_owner_is_not(): void
    {
        $this->report(['files' => [UploadedFile::fake()->create('result.pdf', 50, 'application/pdf')]]);
        $attachment = Attachment::firstOrFail();

        $this->actingAs($this->client)->get(route('attachments.show', $attachment));
        $this->assertDatabaseCount('audit_logs', 0);

        $doctor = $this->doctor();
        $this->actingAs($doctor->user)->get(route('attachments.show', $attachment));
        $this->assertDatabaseHas('audit_logs', ['action' => 'attachment.viewed', 'user_id' => $doctor->user_id, 'auditable_id' => $attachment->id]);
    }

    public function test_downloads_are_forced_attachments_with_safe_headers(): void
    {
        $this->report(['files' => [UploadedFile::fake()->create('result.pdf', 50, 'application/pdf')]]);

        $this->actingAs($this->client)->get(route('attachments.show', Attachment::firstOrFail()))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('content-disposition');
    }

    public function test_a_missing_file_gives_404_not_an_error(): void
    {
        $this->report(['files' => [UploadedFile::fake()->create('result.pdf', 50, 'application/pdf')]]);
        $attachment = Attachment::firstOrFail();
        Storage::disk('local')->delete($attachment->path);

        $this->actingAs($this->client)->get(route('attachments.show', $attachment))->assertNotFound();
    }

    // ---- care team triage ------------------------------------------------------------------------------

    public function test_the_care_team_sees_severe_reports_first_and_triages_them(): void
    {
        $care = $this->staff(Role::CareTeam);
        SideEffectReport::factory()->create(['symptom' => 'عرض خفيف', 'severity' => 'mild', 'occurred_at' => now()->subDays(2)]);
        SideEffectReport::factory()->create(['symptom' => 'عرض شديد', 'severity' => 'severe', 'occurred_at' => now()->subHour()]);
        SideEffectReport::factory()->create(['symptom' => 'عرض متوسط', 'severity' => 'moderate', 'occurred_at' => now()->subDay()]);

        $html = $this->actingAs($care)->get(route('care.side-effects.index'))->assertOk()->getContent();

        $this->assertLessThan(mb_strpos($html, 'عرض متوسط'), mb_strpos($html, 'عرض شديد'));
        $this->assertLessThan(mb_strpos($html, 'عرض خفيف'), mb_strpos($html, 'عرض متوسط'));
    }

    public function test_listing_symptoms_is_audited_once_per_page_with_only_ids(): void
    {
        $care = $this->staff(Role::CareTeam);
        SideEffectReport::factory()->count(3)->create();

        $this->actingAs($care)->get(route('care.side-effects.index'));

        $log = AuditLog::where('action', 'side_effect.listed')->sole();
        $this->assertCount(3, $log->metadata['report_ids']);
        $this->assertSame($care->id, $log->user_id);
    }

    public function test_the_care_team_marks_a_report_reviewed_and_the_client_reads_the_reply(): void
    {
        $care = $this->staff(Role::CareTeam);
        $report = SideEffectReport::factory()->create(['user_id' => $this->client->id]);

        $this->actingAs($care)->put(route('care.side-effects.update', $report), ['status' => 'reviewed', 'response_note' => 'اشرب الماء وراقب الأعراض'])->assertRedirect();

        $this->assertSame(SideEffectStatus::Reviewed, $report->fresh()->status);
        $this->assertSame($care->id, $report->fresh()->handled_by);
        $this->actingAs($this->client)->get(route('account.side-effects.index'))->assertSee('اشرب الماء وراقب الأعراض');
    }

    public function test_escalating_creates_one_urgent_doctor_request_even_if_repeated(): void
    {
        $care = $this->staff(Role::CareTeam);
        $report = SideEffectReport::factory()->create(['user_id' => $this->client->id]);

        $this->actingAs($care)->put(route('care.side-effects.update', $report), ['status' => 'escalated']);
        $this->actingAs($care)->put(route('care.side-effects.update', $report), ['status' => 'escalated', 'response_note' => 'تحديث']);

        $this->assertSame(1, ContactRequest::where('source', 'side_effect')->count());
    }

    public function test_the_triage_status_must_be_reviewed_or_escalated(): void
    {
        $care = $this->staff(Role::CareTeam);
        $report = SideEffectReport::factory()->create();

        $this->actingAs($care)->put(route('care.side-effects.update', $report), ['status' => 'new'])->assertSessionHasErrors('status');
    }

    public function test_support_and_admin_cannot_read_or_triage_side_effects(): void
    {
        $report = SideEffectReport::factory()->create();

        foreach ([$this->admin(), $this->staff(Role::Support)] as $user) {
            $this->actingAs($user)->get(route('care.side-effects.index'))->assertForbidden();
            $this->actingAs($user)->put(route('care.side-effects.update', $report), ['status' => 'reviewed'])->assertForbidden();
        }
    }

    // ---- contact requests ------------------------------------------------------------------------------

    public function test_a_client_asks_the_care_team_for_contact_and_the_team_is_alerted(): void
    {
        Notification::fake();
        $care = $this->staff(Role::CareTeam);

        $this->actingAs($this->client)->post(route('account.contact-requests.store'), ['target' => 'care_team', 'reason' => 'أحتاج إلى مساعدة في جدول الجرعات'])
            ->assertRedirect(route('account.contact-requests.index'));

        $request = ContactRequest::firstOrFail();
        $this->assertSame(ContactTarget::CareTeam, $request->target);
        $this->assertSame('normal', $request->priority);
        Notification::assertSentTo($care, GenericNotification::class);
    }

    public function test_a_request_to_the_doctor_is_not_pushed_to_the_care_team_unless_urgent(): void
    {
        Notification::fake();
        $care = $this->staff(Role::CareTeam);

        $this->actingAs($this->client)->post(route('account.contact-requests.store'), ['target' => 'doctor', 'reason' => 'سؤال عن الخطة القادمة']);

        Notification::assertNotSentTo($care, GenericNotification::class);
    }

    public function test_contact_request_input_is_validated(): void
    {
        $this->actingAs($this->client)->post(route('account.contact-requests.store'), ['target' => 'nobody', 'reason' => 'x'])->assertSessionHasErrors(['target', 'reason']);
    }

    public function test_doctors_see_only_requests_addressed_to_a_doctor_and_care_sees_all(): void
    {
        ContactRequest::factory()->create(['target' => 'doctor', 'reason' => 'طلب موجه للطبيب']);
        ContactRequest::factory()->create(['target' => 'care_team', 'reason' => 'طلب موجه للرعاية']);

        $this->actingAs($this->doctor()->user)->get(route('care.contact-requests.index'))->assertSee('طلب موجه للطبيب')->assertDontSee('طلب موجه للرعاية');
        $this->actingAs($this->staff(Role::CareTeam))->get(route('care.contact-requests.index'))->assertSee('طلب موجه للطبيب')->assertSee('طلب موجه للرعاية');
    }

    public function test_handling_a_request_notifies_the_client_when_resolved(): void
    {
        Notification::fake();
        $care = $this->staff(Role::CareTeam);
        $request = ContactRequest::factory()->create(['user_id' => $this->client->id]);

        $this->actingAs($care)->put(route('care.contact-requests.update', $request), ['status' => 'in_progress'])->assertRedirect();
        Notification::assertNothingSent();

        $this->actingAs($care)->put(route('care.contact-requests.update', $request), ['status' => 'resolved', 'response_note' => 'تم التواصل معك هاتفيًا']);

        $request->refresh();
        $this->assertSame(ContactRequestStatus::Resolved, $request->status);
        $this->assertNotNull($request->resolved_at);
        $this->assertSame($care->id, $request->assigned_to);
        Notification::assertSentTo($this->client, GenericNotification::class);
    }

    public function test_a_doctor_cannot_handle_a_request_addressed_to_the_care_team(): void
    {
        $request = ContactRequest::factory()->create(['target' => 'care_team']);

        $this->actingAs($this->doctor()->user)->put(route('care.contact-requests.update', $request), ['status' => 'resolved'])->assertForbidden();
        $this->assertSame(ContactRequestStatus::Open, $request->fresh()->status);
    }

    public function test_reason_text_is_encrypted_at_rest(): void
    {
        ContactRequest::factory()->create(['reason' => 'سبب سري للتواصل']);

        $this->assertStringNotContainsString('سري', (string) DB::table('contact_requests')->value('reason'));
    }

    public function test_the_side_effects_page_always_shows_the_emergency_guidance(): void
    {
        $this->actingAs($this->client)->get(route('account.side-effects.index'))->assertSee('اتزان ليست خدمة طوارئ.')->assertSee('997');
        $this->actingAs($this->client)->get(route('account.contact-requests.index'))->assertSee('997');
    }
}
