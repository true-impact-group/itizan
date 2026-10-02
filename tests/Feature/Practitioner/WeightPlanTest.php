<?php

namespace Tests\Feature\Practitioner;

use App\Enums\PlanStatus;
use App\Models\Measurement;
use App\Models\Practitioner;
use App\Models\WeightPlan;
use App\Notifications\GenericNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WeightPlanTest extends TestCase
{
    private function planPayload(array $overrides = []): array
    {
        return [
            'target_weight_kg' => '80',
            'target_waist_cm' => '95',
            'doctor_notes' => 'نبدأ بخطوات صغيرة.',
            'next_steps' => ['سجّل وزنك أسبوعيًا', '', 'أكمل التحليل'],
            'review_on' => now()->addMonth()->toDateString(),
            ...$overrides,
        ];
    }

    public function test_a_doctor_creates_the_plan_after_approving_the_assessment(): void
    {
        Notification::fake();
        $doctor = $this->doctor();
        $client = $this->approvedClient();

        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload())
            ->assertRedirect(route('practitioner.clients.show', $client));

        $plan = WeightPlan::firstOrFail();
        $this->assertSame(PlanStatus::Active, $plan->status);
        $this->assertSame($doctor->id, $plan->practitioner_id);
        $this->assertSame(80.0, $plan->target_weight_kg);
        $this->assertSame(95.5, $plan->start_weight_kg, 'starts from the assessment weight when there is no later measurement');
        $this->assertSame(['سجّل وزنك أسبوعيًا', 'أكمل التحليل'], $plan->next_steps, 'blank steps are dropped');
        Notification::assertSentTo($client, GenericNotification::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.created']);
    }

    public function test_the_start_weight_is_the_latest_recorded_measurement(): void
    {
        $doctor = $this->doctor();
        $client = $this->approvedClient();
        Measurement::factory()->create(['user_id' => $client->id, 'measured_on' => now()->subDays(3), 'weight_kg' => 92.0]);
        Measurement::factory()->create(['user_id' => $client->id, 'measured_on' => now()->subDay(), 'weight_kg' => 91.0]);

        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload());

        $this->assertSame(91.0, WeightPlan::firstOrFail()->start_weight_kg);
    }

    public function test_a_plan_cannot_be_created_before_the_assessment_is_approved(): void
    {
        $doctor = $this->doctor();
        $client = $this->client();
        $this->submittedAssessment($client);

        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload())
            ->assertSessionHas('error', 'اعتمد التقييم الطبي للعميل أولًا قبل إعداد الخطة.');

        $this->assertDatabaseCount('weight_plans', 0);
    }

    public function test_a_new_plan_closes_the_previous_active_one(): void
    {
        $doctor = $this->doctor();
        $client = $this->approvedClient();
        $old = WeightPlan::factory()->create(['user_id' => $client->id, 'practitioner_id' => $doctor->id]);

        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload(['target_weight_kg' => '75']));

        $this->assertSame(PlanStatus::Completed, $old->fresh()->status);
        $this->assertSame(1, WeightPlan::where('user_id', $client->id)->where('status', 'active')->count());
    }

    public function test_the_database_allows_a_single_active_plan_per_client(): void
    {
        $client = $this->client();
        WeightPlan::factory()->create(['user_id' => $client->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        WeightPlan::factory()->create(['user_id' => $client->id]);
    }

    public function test_plan_input_is_validated(): void
    {
        $doctor = $this->doctor();
        $client = $this->approvedClient();

        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload(['target_weight_kg' => '5']))->assertSessionHasErrors('target_weight_kg');
        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload(['target_weight_kg' => '']))->assertSessionHasErrors('target_weight_kg');
        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload(['review_on' => now()->subDay()->toDateString()]))->assertSessionHasErrors('review_on');
        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload(['next_steps' => array_fill(0, 11, 'خطوة')]))->assertSessionHasErrors('next_steps');
    }

    public function test_only_a_dietitian_can_be_assigned_as_the_plan_dietitian(): void
    {
        $doctor = $this->doctor();
        $otherDoctor = $this->doctor();
        $client = $this->approvedClient();

        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload(['dietitian_id' => $otherDoctor->id]))
            ->assertSessionHasErrors('dietitian_id');

        $dietitian = $this->dietitian();
        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload(['dietitian_id' => $dietitian->id]))->assertRedirect();
        $this->assertSame($dietitian->id, WeightPlan::firstOrFail()->dietitian_id);
    }

    public function test_an_unverified_dietitian_cannot_be_assigned(): void
    {
        $doctor = $this->doctor();
        $client = $this->approvedClient();
        $unverified = Practitioner::factory()->dietitian()->unverified()->create();

        $this->actingAs($doctor->user)->post(route('practitioner.plans.store', $client), $this->planPayload(['dietitian_id' => $unverified->id]))
            ->assertSessionHas('error', 'أخصائي التغذية المختار غير متاح.');
    }

    public function test_an_unverified_doctor_cannot_create_a_plan(): void
    {
        $unverified = $this->doctor(['license_verified_at' => null]);

        $this->actingAs($unverified->user)->post(route('practitioner.plans.store', $this->approvedClient()), $this->planPayload())
            ->assertSessionHas('error', 'فقط الطبيب الموثّق يمكنه إعداد الخطة.');
    }

    public function test_a_dietitian_cannot_create_a_plan(): void
    {
        $this->actingAs($this->dietitian()->user)->post(route('practitioner.plans.store', $this->approvedClient()), $this->planPayload())->assertForbidden();
        $this->actingAs($this->dietitian()->user)->get(route('practitioner.plans.create', $this->approvedClient()))->assertForbidden();
    }

    public function test_a_doctor_edits_the_medical_parts_of_the_plan(): void
    {
        $doctor = $this->doctor();
        $plan = WeightPlan::factory()->create(['practitioner_id' => $doctor->id]);

        $this->actingAs($doctor->user)->put(route('practitioner.plans.update', $plan), [
            'target_weight_kg' => '78', 'doctor_notes' => 'ملاحظات محدثة', 'next_steps' => ['خطوة جديدة'], 'status' => 'paused',
            'nutrition_guidance' => 'توجيه', 'review_on' => now()->addMonth()->toDateString(),
        ])->assertRedirect();

        $plan->refresh();
        $this->assertSame(78.0, $plan->target_weight_kg);
        $this->assertSame('ملاحظات محدثة', $plan->doctor_notes);
        $this->assertSame(PlanStatus::Paused, $plan->status);
        $this->assertSame(['خطوة جديدة'], $plan->next_steps);
    }

    public function test_a_dietitian_can_only_change_the_nutrition_guidance_of_an_assigned_plan(): void
    {
        $dietitian = $this->dietitian();
        $plan = WeightPlan::factory()->create(['dietitian_id' => $dietitian->id, 'target_weight_kg' => 80, 'doctor_notes' => 'ملاحظة الطبيب الأصلية']);

        $this->actingAs($dietitian->user)->put(route('practitioner.plans.update', $plan), [
            'nutrition_guidance' => 'وجبات متوازنة', 'target_weight_kg' => '60', 'doctor_notes' => 'محاولة تعديل', 'status' => 'completed',
        ])->assertRedirect();

        $plan->refresh();
        $this->assertSame('وجبات متوازنة', $plan->nutrition_guidance);
        $this->assertSame(80.0, $plan->target_weight_kg);
        $this->assertSame('ملاحظة الطبيب الأصلية', $plan->doctor_notes);
        $this->assertSame(PlanStatus::Active, $plan->status);
    }

    public function test_a_dietitian_cannot_edit_a_plan_not_assigned_to_them(): void
    {
        $plan = WeightPlan::factory()->create(['dietitian_id' => $this->dietitian()->id]);

        $this->actingAs($this->dietitian()->user)->put(route('practitioner.plans.update', $plan), ['nutrition_guidance' => 'x'])->assertForbidden();
        $this->actingAs($this->dietitian()->user)->get(route('practitioner.plans.edit', $plan))->assertForbidden();
    }

    public function test_the_edit_form_only_offers_the_nutrition_field_to_a_dietitian(): void
    {
        $dietitian = $this->dietitian();
        $plan = WeightPlan::factory()->create(['dietitian_id' => $dietitian->id]);

        $this->actingAs($dietitian->user)->get(route('practitioner.plans.edit', $plan))
            ->assertOk()
            ->assertSee('التوجيه الغذائي')
            ->assertDontSee('name="target_weight_kg"', false)
            ->assertDontSee('name="doctor_notes"', false);
    }

    public function test_the_client_sees_the_plan_progress_notes_steps_and_nutrition_guidance(): void
    {
        $dietitian = $this->dietitian();
        $client = $this->client();
        WeightPlan::factory()->create([
            'user_id' => $client->id, 'dietitian_id' => $dietitian->id, 'start_weight_kg' => 95, 'target_weight_kg' => 80,
            'doctor_notes' => 'ملاحظة طبيبك الظاهرة', 'next_steps' => ['خطوة مطلوبة ظاهرة'], 'nutrition_guidance' => 'توجيه غذائي ظاهر',
        ]);
        Measurement::factory()->create(['user_id' => $client->id, 'weight_kg' => 90.0]);

        $this->actingAs($client)->get(route('account.plan.show'))
            ->assertOk()
            ->assertSee('95')->assertSee('90')->assertSee('80')
            ->assertSee('33%')
            ->assertSee('ملاحظة طبيبك الظاهرة')
            ->assertSee('خطوة مطلوبة ظاهرة')
            ->assertSee('توجيه غذائي ظاهر');
    }

    public function test_a_client_without_a_plan_is_told_what_happens_next(): void
    {
        $client = $this->client();
        $this->submittedAssessment($client);

        $this->actingAs($client)->get(route('account.plan.show'))->assertSee('تقييمك عند الطبيب. ستظهر خطتك هنا بعد المراجعة.');
    }

    public function test_progress_is_direction_agnostic_and_clamped(): void
    {
        $loss = new WeightPlan(['start_weight_kg' => 100, 'target_weight_kg' => 80]);
        $this->assertSame(50, $loss->progressAt(90.0)['percent']);
        $this->assertSame(100, $loss->progressAt(75.0)['percent'], 'past the goal is capped at 100%');
        $this->assertSame(0, $loss->progressAt(105.0)['percent'], 'moving away from the goal is floored at 0%');
        $this->assertSame(-10.0, $loss->progressAt(90.0)['change']);
        $this->assertSame(10.0, $loss->progressAt(90.0)['remaining']);

        $gain = new WeightPlan(['start_weight_kg' => 50, 'target_weight_kg' => 60]);
        $this->assertSame(50, $gain->progressAt(55.0)['percent']);

        $same = new WeightPlan(['start_weight_kg' => 70, 'target_weight_kg' => 70]);
        $this->assertSame(100, $same->progressAt(70.0)['percent']);
    }
}
