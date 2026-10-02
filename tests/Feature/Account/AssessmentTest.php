<?php

namespace Tests\Feature\Account;

use App\Enums\AssessmentStatus;
use App\Enums\ConsentType;
use App\Models\Measurement;
use App\Models\MedicalAssessment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->client(['gender' => 'male']);
    }

    private function answers(array $overrides = []): array
    {
        return [...array_fill_keys(array_keys(config('itizan.safety_questions')), 'no'), ...$overrides];
    }

    private function submission(array $overrides = []): array
    {
        return [
            'action' => 'submit',
            'height_cm' => '170',
            'weight_kg' => '95.5',
            'waist_cm' => '105',
            'target_weight_kg' => '80',
            'medical_history' => 'ارتفاع ضغط سابق',
            'chronic_conditions' => 'لا يوجد',
            'current_medications' => 'لا يوجد',
            'allergies' => 'حساسية من البنسلين',
            'goals' => 'خسارة وزن صحية',
            'safety_answers' => $this->answers(),
            'medical_consent' => '1',
            ...$overrides,
        ];
    }

    private function submitAs(array $data)
    {
        return $this->actingAs($this->client)->put(route('account.assessment.update'), $data);
    }

    public function test_the_assessment_page_shows_the_form_with_all_safety_questions(): void
    {
        $response = $this->actingAs($this->client)->get(route('account.assessment.show'))->assertOk();

        foreach (config('itizan.safety_questions') as $question) {
            $response->assertSee($question['text']);
        }
        $response->assertSee('اتزان ليست خدمة طوارئ.');
    }

    public function test_a_partial_draft_can_be_saved_and_resumed(): void
    {
        $this->submitAs(['action' => 'save', 'height_cm' => '170', 'weight_kg' => '95.5'])->assertRedirect(route('account.assessment.show'));

        $assessment = MedicalAssessment::firstOrFail();
        $this->assertSame(AssessmentStatus::Draft, $assessment->status);
        $this->assertNull($assessment->submitted_at);

        $this->actingAs($this->client)->get(route('account.assessment.show'))->assertSee('value="170"', false);
    }

    public function test_bmi_is_calculated_from_height_and_weight(): void
    {
        $this->submitAs(['action' => 'save', 'height_cm' => '170', 'weight_kg' => '95.5']);

        $this->assertSame(33.0, MedicalAssessment::firstOrFail()->bmi);
    }

    public function test_submitting_sends_the_assessment_to_the_doctor_and_records_the_medical_data_consent(): void
    {
        $this->submitAs($this->submission())->assertRedirect();

        $assessment = MedicalAssessment::firstOrFail();
        $this->assertSame(AssessmentStatus::Submitted, $assessment->status);
        $this->assertNotNull($assessment->submitted_at);
        $this->assertFalse($assessment->has_red_flags);
        $this->assertDatabaseHas('consent_records', ['user_id' => $this->client->id, 'type' => ConsentType::MedicalData->value, 'granted' => true]);
    }

    public function test_submitting_records_the_first_measurement_for_the_progress_chart(): void
    {
        $this->submitAs($this->submission());

        $measurement = Measurement::firstOrFail();
        $this->assertSame(95.5, $measurement->weight_kg);
        $this->assertSame(105.0, $measurement->waist_cm);
        $this->assertTrue($measurement->measured_on->isToday());
    }

    public function test_the_essential_information_is_required_to_submit(): void
    {
        $this->submitAs($this->submission(['height_cm' => '', 'weight_kg' => '', 'waist_cm' => '', 'goals' => '', 'allergies' => '']))
            ->assertSessionHasErrors(['height_cm', 'weight_kg', 'waist_cm', 'goals', 'allergies']);

        $this->assertSame(0, MedicalAssessment::where('status', 'submitted')->count());
    }

    public function test_every_safety_question_must_be_answered_to_submit(): void
    {
        $answers = $this->answers();
        unset($answers['pancreatitis']);

        $this->submitAs($this->submission(['safety_answers' => $answers]))->assertSessionHasErrors('safety_answers');
    }

    public function test_the_medical_consent_is_required_to_submit_but_not_to_save_a_draft(): void
    {
        $this->submitAs($this->submission(['medical_consent' => '0']))->assertSessionHasErrors('medical_consent');

        $this->submitAs(['action' => 'save', 'height_cm' => '170'])->assertSessionHasNoErrors();
    }

    public function test_measurements_must_be_plausible(): void
    {
        $this->submitAs($this->submission(['height_cm' => '50', 'weight_kg' => '5', 'waist_cm' => '500']))
            ->assertSessionHasErrors(['height_cm', 'weight_kg', 'waist_cm']);
    }

    public function test_a_yes_to_a_safety_question_flags_the_case_for_the_doctor_without_blocking_the_client(): void
    {
        $this->submitAs($this->submission(['safety_answers' => $this->answers(['pancreatitis' => 'yes'])]))->assertRedirect();

        $assessment = MedicalAssessment::firstOrFail();
        $this->assertTrue($assessment->has_red_flags);
        $this->assertSame(AssessmentStatus::Submitted, $assessment->status);
    }

    public function test_pregnancy_status_is_required_for_women_when_submitting(): void
    {
        $woman = $this->client(['gender' => 'female']);

        $this->actingAs($woman)->put(route('account.assessment.update'), $this->submission())->assertSessionHasErrors('pregnancy_status');

        $this->actingAs($woman)->put(route('account.assessment.update'), $this->submission(['pregnancy_status' => 'not_pregnant', 'is_breastfeeding' => '0']))
            ->assertSessionHasNoErrors();
    }

    public function test_men_are_not_asked_about_pregnancy(): void
    {
        $this->actingAs($this->client)->get(route('account.assessment.show'))->assertDontSee('الحمل والرضاعة');
    }

    public function test_clinical_text_is_encrypted_at_rest(): void
    {
        $this->submitAs($this->submission(['medical_history' => 'سر طبي لا يجب أن يظهر في قاعدة البيانات']));

        $raw = DB::table('medical_assessments')->first();

        foreach (['medical_history', 'chronic_conditions', 'current_medications', 'allergies', 'goals', 'safety_answers'] as $column) {
            $this->assertStringNotContainsString('سر طبي', (string) $raw->{$column});
            $this->assertStringStartsWith('eyJpdiI6', (string) $raw->{$column}, "{$column} should be an encrypted payload");
        }
        $this->assertSame('سر طبي لا يجب أن يظهر في قاعدة البيانات', MedicalAssessment::firstOrFail()->medical_history);
    }

    public function test_a_submitted_assessment_is_locked_while_the_doctor_reviews_it(): void
    {
        $this->submitAs($this->submission());

        $this->submitAs($this->submission(['goals' => 'تغيير بعد الإرسال']))
            ->assertSessionHas('error', 'تقييمك قيد مراجعة الطبيب حاليًا ولا يمكن تعديله.');

        $this->assertSame('خسارة وزن صحية', MedicalAssessment::firstOrFail()->goals);
        $this->actingAs($this->client)->get(route('account.assessment.show'))->assertSee('بانتظار مراجعة الطبيب');
    }

    public function test_a_client_cannot_set_review_fields_through_the_form(): void
    {
        $doctor = $this->doctor();
        $this->submitAs($this->submission(['status' => 'approved', 'reviewed_by' => $doctor->user_id, 'reviewer_notes' => 'معتمد', 'has_red_flags' => 1]));

        $assessment = MedicalAssessment::firstOrFail();
        $this->assertSame(AssessmentStatus::Submitted, $assessment->status);
        $this->assertNull($assessment->reviewed_by);
        $this->assertNull($assessment->reviewer_notes);
        $this->assertFalse($assessment->has_red_flags);
    }

    public function test_when_the_doctor_asks_for_more_information_the_client_can_edit_and_resubmit(): void
    {
        MedicalAssessment::factory()->create([
            'user_id' => $this->client->id, 'status' => AssessmentStatus::NeedsInfo, 'reviewer_notes' => 'أضف نتيجة تحليل السكر',
        ]);

        $this->actingAs($this->client)->get(route('account.assessment.show'))
            ->assertSee('أضف نتيجة تحليل السكر')
            ->assertSee('إرسال إلى الطبيب');

        $this->submitAs($this->submission(['available_labs' => 'سكر 5.4']))->assertRedirect();

        $assessment = MedicalAssessment::firstOrFail();
        $this->assertSame(AssessmentStatus::Submitted, $assessment->status);
        $this->assertNull($assessment->reviewer_notes);
        $this->assertSame(1, MedicalAssessment::count(), 'the same assessment is reused');
    }

    public function test_after_a_decision_the_client_can_start_an_updated_assessment_without_losing_the_old_one(): void
    {
        $approved = MedicalAssessment::factory()->approved()->create(['user_id' => $this->client->id, 'weight_kg' => 95.5]);

        $this->actingAs($this->client)->get(route('account.assessment.show'))->assertSee('تحديث التقييم');
        $this->actingAs($this->client)->get(route('account.assessment.show', ['update' => 1]))->assertSee('إرسال إلى الطبيب');

        $this->submitAs($this->submission(['weight_kg' => '90']));

        $this->assertSame(2, MedicalAssessment::count());
        $this->assertSame(AssessmentStatus::Approved, $approved->fresh()->status);
        $this->assertSame(1, MedicalAssessment::where('status', 'submitted')->count());
    }

    public function test_the_database_allows_only_one_open_assessment_per_client(): void
    {
        MedicalAssessment::factory()->draft()->create(['user_id' => $this->client->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        MedicalAssessment::factory()->create(['user_id' => $this->client->id]);
    }

    public function test_each_client_only_sees_their_own_assessment(): void
    {
        MedicalAssessment::factory()->create(['user_id' => $this->client->id, 'goals' => 'هدف سري لعميل أول']);

        $other = $this->client();
        $this->actingAs($other)->get(route('account.assessment.show'))->assertOk()->assertDontSee('هدف سري لعميل أول');
    }

    public function test_the_read_only_summary_shows_the_bmi_with_a_note_that_the_doctor_interprets_it(): void
    {
        MedicalAssessment::factory()->create(['user_id' => $this->client->id, 'bmi' => 33.0]);

        $this->actingAs($this->client)->get(route('account.assessment.show'))
            ->assertSee('33')
            ->assertSee('سمنة - الدرجة الأولى')
            ->assertSee('يفسّره الطبيب في سياق حالتك');
    }
}
