<?php

namespace Tests\Feature\Practitioner;

use App\Enums\PrescriptionStatus;
use App\Enums\Role;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\WeightPlan;
use App\Notifications\GenericNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));
    }

    private function payload(array $overrides = []): array
    {
        return [
            'medication_name' => 'دواء تجريبي',
            'dose' => '0.25 ملغ',
            'instructions' => 'يؤخذ صباحًا',
            'interval_days' => '7',
            'dose_time' => '09:00',
            'starts_on' => today()->toDateString(),
            'pre_dose_questions' => [
                ['text' => 'هل تشعر بغثيان شديد؟', 'alert_on' => 'yes'],
                ['text' => '', 'alert_on' => 'yes'],
            ],
            ...$overrides,
        ];
    }

    public function test_a_verified_doctor_prescribes_for_a_client_with_an_approved_assessment(): void
    {
        Notification::fake();
        $doctor = $this->doctor();
        $client = $this->approvedClient();

        $this->actingAs($doctor->user)->post(route('practitioner.prescriptions.store', $client), $this->payload())
            ->assertRedirect(route('practitioner.clients.show', $client));

        $rx = Prescription::firstOrFail();
        $this->assertSame($client->id, $rx->user_id);
        $this->assertSame($doctor->id, $rx->practitioner_id);
        $this->assertSame('دواء تجريبي', $rx->medication_name);
        $this->assertSame(PrescriptionStatus::Active, $rx->status);
        $this->assertTrue($rx->next_dose_at->equalTo(Carbon::parse('2026-10-05 09:00')), 'first dose is today at 09:00');
        $this->assertCount(1, $rx->preDoseQuestions(), 'blank questions are dropped');
        Notification::assertSentTo($client, GenericNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'prescription.created', 'user_id' => $doctor->user_id]);
    }

    public function test_the_first_dose_moves_to_the_next_occurrence_when_the_start_is_in_the_past(): void
    {
        $doctor = $this->doctor();
        $client = $this->approvedClient();

        // Dose time already passed today, so the first dose is the next weekly slot.
        $this->actingAs($doctor->user)->post(route('practitioner.prescriptions.store', $client), $this->payload(['dose_time' => '07:00']));

        $this->assertTrue(Prescription::firstOrFail()->next_dose_at->equalTo(Carbon::parse('2026-10-12 07:00')));
    }

    public function test_name_dose_and_instructions_are_encrypted_at_rest(): void
    {
        $this->actingAs($this->doctor()->user)->post(route('practitioner.prescriptions.store', $this->approvedClient()), $this->payload());

        $raw = DB::table('prescriptions')->first();
        foreach (['medication_name', 'dose', 'instructions'] as $column) {
            $this->assertStringStartsWith('eyJpdiI6', $raw->{$column});
        }
        $this->assertStringNotContainsString('دواء تجريبي', json_encode($raw, JSON_UNESCAPED_UNICODE));
    }

    public function test_no_prescription_without_an_approved_assessment(): void
    {
        $doctor = $this->doctor();
        $client = $this->client();
        $this->submittedAssessment($client);

        $this->actingAs($doctor->user)->post(route('practitioner.prescriptions.store', $client), $this->payload())
            ->assertSessionHas('error', 'لا يمكن كتابة وصفة قبل اعتماد التقييم الطبي للعميل.');

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_an_unverified_doctor_cannot_prescribe(): void
    {
        $unverified = $this->doctor(['license_verified_at' => null]);

        $this->actingAs($unverified->user)->post(route('practitioner.prescriptions.store', $this->approvedClient()), $this->payload())
            ->assertSessionHas('error', 'فقط الطبيب الموثّق يمكنه كتابة الوصفات.');

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_nobody_but_a_doctor_can_prescribe(): void
    {
        $client = $this->approvedClient();

        foreach ([$this->client(), $this->dietitian()->user, $this->staff(Role::CareTeam), $this->admin(), $this->staff(Role::Support), $this->pharmacist()] as $user) {
            $this->actingAs($user)->post(route('practitioner.prescriptions.store', $client), $this->payload())->assertForbidden();
        }

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_the_platform_never_creates_prescriptions_on_its_own(): void
    {
        $client = $this->approvedClient();

        // Completing assessments, plans, appointments, orders and payments leaves no prescription behind.
        $this->subscribe($client);
        $this->actingAs($this->doctor()->user)->post(route('practitioner.assessments.review', $this->submittedAssessment($this->client())), ['decision' => 'approved']);

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_prescription_input_is_validated(): void
    {
        $doctor = $this->doctor();
        $client = $this->approvedClient();
        $post = fn (array $o) => $this->actingAs($doctor->user)->post(route('practitioner.prescriptions.store', $client), $this->payload($o));

        $post(['medication_name' => ''])->assertSessionHasErrors('medication_name');
        $post(['dose' => ''])->assertSessionHasErrors('dose');
        $post(['interval_days' => '0'])->assertSessionHasErrors('interval_days');
        $post(['interval_days' => '90'])->assertSessionHasErrors('interval_days');
        $post(['dose_time' => '9am'])->assertSessionHasErrors('dose_time');
        $post(['starts_on' => today()->subDay()->toDateString()])->assertSessionHasErrors('starts_on');
        $post(['ends_on' => today()->subDay()->toDateString()])->assertSessionHasErrors('ends_on');
        $post(['pre_dose_questions' => array_fill(0, 6, ['text' => 'سؤال', 'alert_on' => 'yes'])])->assertSessionHasErrors('pre_dose_questions');
        $post(['pre_dose_questions' => [['text' => 'سؤال', 'alert_on' => 'maybe']]])->assertSessionHasErrors('pre_dose_questions.0.alert_on');
        $post(['product_id' => Product::factory()->create()->id])->assertSessionHasErrors('product_id'); // a supplement is not a medication

        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_a_prescription_can_be_linked_to_a_medication_in_the_catalogue(): void
    {
        $product = Product::factory()->medication()->create();

        $this->actingAs($this->doctor()->user)->post(route('practitioner.prescriptions.store', $this->approvedClient()), $this->payload(['product_id' => $product->id]));

        $this->assertSame($product->id, Prescription::firstOrFail()->product_id);
    }

    public function test_the_client_has_no_way_to_change_a_prescription_or_its_dose(): void
    {
        $client = $this->approvedClient();
        $rx = Prescription::factory()->create(['user_id' => $client->id, 'dose' => '0.25 ملغ']);

        $this->assertFalse($client->can('update', $rx));
        $this->assertFalse($client->can('create', Prescription::class));

        // Every write verb against the prescription URLs is either forbidden or does not exist.
        $this->actingAs($client)->put(route('account.medications.show', $rx), ['dose' => '10 ملغ'])->assertStatus(405);
        $this->actingAs($client)->patch(route('account.medications.show', $rx), ['dose' => '10 ملغ'])->assertStatus(405);
        $this->actingAs($client)->delete(route('account.medications.show', $rx))->assertStatus(405);
        $this->actingAs($client)->post(route('practitioner.prescriptions.stop', $rx), ['reason' => 'أريد إيقافه'])->assertForbidden();
        $this->actingAs($client)->post(route('practitioner.prescriptions.store', $client), $this->payload())->assertForbidden();

        $this->assertSame('0.25 ملغ', $rx->fresh()->dose);
    }

    public function test_the_dose_logging_endpoint_ignores_any_attempt_to_send_a_new_dose_or_interval(): void
    {
        $client = $this->client();
        $rx = Prescription::factory()->due()->create(['user_id' => $client->id, 'dose' => '0.25 ملغ', 'interval_days' => 7]);

        $this->actingAs($client)->post(route('account.medications.doses.store', $rx), [
            'status' => 'taken', 'dose' => '5 ملغ', 'interval_days' => 1, 'next_dose_at' => now()->addMinute()->toDateTimeString(),
        ]);

        $rx->refresh();
        $this->assertSame('0.25 ملغ', $rx->dose);
        $this->assertSame(7, $rx->interval_days);
        $this->assertTrue($rx->next_dose_at->greaterThan(now()->addDays(6)), 'schedule advanced by the doctor-set interval only');
    }

    public function test_only_the_issuing_doctor_can_stop_a_prescription(): void
    {
        Notification::fake();
        $issuer = $this->doctor();
        $other = $this->doctor();
        $client = $this->approvedClient();
        $rx = Prescription::factory()->create(['user_id' => $client->id, 'practitioner_id' => $issuer->id]);

        $this->actingAs($other->user)->post(route('practitioner.prescriptions.stop', $rx), ['reason' => 'سبب'])->assertForbidden();
        $this->assertSame(PrescriptionStatus::Active, $rx->fresh()->status);

        $this->actingAs($issuer->user)->post(route('practitioner.prescriptions.stop', $rx), ['reason' => 'ظهور أعراض جانبية'])->assertRedirect();

        $rx->refresh();
        $this->assertSame(PrescriptionStatus::Stopped, $rx->status);
        $this->assertNull($rx->next_dose_at);
        $this->assertSame('ظهور أعراض جانبية', $rx->stop_reason);
        Notification::assertSentTo($client, GenericNotification::class);
    }

    public function test_stopping_requires_a_reason_and_cannot_be_repeated(): void
    {
        $issuer = $this->doctor();
        $rx = Prescription::factory()->create(['practitioner_id' => $issuer->id]);

        $this->actingAs($issuer->user)->post(route('practitioner.prescriptions.stop', $rx), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->actingAs($issuer->user)->post(route('practitioner.prescriptions.stop', $rx), ['reason' => 'سبب أول']);
        $this->actingAs($issuer->user)->post(route('practitioner.prescriptions.stop', $rx), ['reason' => 'سبب ثان'])
            ->assertSessionHas('error', 'هذه الوصفة متوقفة بالفعل.');
    }

    public function test_the_prescription_form_is_hidden_until_the_assessment_is_approved(): void
    {
        $doctor = $this->doctor();
        $pending = $this->client();
        $this->submittedAssessment($pending);

        $this->actingAs($doctor->user)->get(route('practitioner.clients.show', $pending))->assertOk()->assertDontSee('كتابة وصفة جديدة');
        $this->actingAs($doctor->user)->get(route('practitioner.clients.show', $this->approvedClient()))->assertSee('كتابة وصفة جديدة');
    }

    public function test_a_dietitian_does_not_see_prescriptions_or_labs_in_the_client_file(): void
    {
        $dietitian = $this->dietitian();
        $client = $this->client();
        WeightPlan::factory()->create(['user_id' => $client->id, 'dietitian_id' => $dietitian->id]);
        Prescription::factory()->create(['user_id' => $client->id, 'medication_name' => 'دواء سري']);

        $this->actingAs($dietitian->user)->get(route('practitioner.clients.show', $client))
            ->assertOk()
            ->assertDontSee('دواء سري')
            ->assertDontSee('الوصفات');
    }
}
