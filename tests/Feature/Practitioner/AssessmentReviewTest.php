<?php

namespace Tests\Feature\Practitioner;

use App\Enums\AssessmentStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\MedicalAssessment;
use App\Models\User;
use App\Models\WeightPlan;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssessmentReviewTest extends TestCase
{
    public function test_the_review_queue_shows_safety_flagged_cases_first_then_oldest_first(): void
    {
        $doctor = $this->doctor();
        $plain = $this->submittedAssessment($this->client(['name' => 'عميل عادي']), ['submitted_at' => now()->subDays(3)]);
        $flagged = MedicalAssessment::factory()->withRedFlag()->create(['user_id' => $this->client(['name' => 'عميل تنبيه'])->id, 'submitted_at' => now()->subHour()]);

        $response = $this->actingAs($doctor->user)->get(route('practitioner.assessments.index'))->assertOk();

        $html = $response->getContent();
        $this->assertLessThan(mb_strpos($html, 'عميل عادي'), mb_strpos($html, 'عميل تنبيه'));
        $response->assertSee('⚠ يحتاج انتباهًا');
    }

    public function test_the_queue_can_be_filtered_to_safety_alerts_and_to_other_statuses(): void
    {
        $doctor = $this->doctor();
        $this->submittedAssessment($this->client(['name' => 'عميل عادي']));
        MedicalAssessment::factory()->withRedFlag()->create(['user_id' => $this->client(['name' => 'عميل تنبيه'])->id]);
        MedicalAssessment::factory()->approved()->create(['user_id' => $this->client(['name' => 'عميل معتمد'])->id]);

        $this->actingAs($doctor->user)->get(route('practitioner.assessments.index', ['red_flags' => 1]))
            ->assertSee('عميل تنبيه')->assertDontSee('عميل عادي');

        $this->actingAs($doctor->user)->get(route('practitioner.assessments.index', ['status' => 'approved']))
            ->assertSee('عميل معتمد')->assertDontSee('عميل تنبيه');
    }

    public function test_opening_an_assessment_shows_the_clinical_detail_and_writes_an_audit_entry(): void
    {
        $doctor = $this->doctor();
        $client = $this->client(['name' => 'منى']);
        $assessment = MedicalAssessment::factory()->withRedFlag()->create([
            'user_id' => $client->id, 'allergies' => 'حساسية من المكسرات', 'goals' => 'هدف العميل',
        ]);

        $this->actingAs($doctor->user)->get(route('practitioner.assessments.show', $assessment))
            ->assertOk()
            ->assertSee('حساسية من المكسرات')
            ->assertSee('هل سبق أن أصبت بالتهاب البنكرياس؟')
            ->assertSee('⚠ توجد إجابات تحتاج انتباهًا');

        $log = AuditLog::where('action', 'assessment.viewed')->firstOrFail();
        $this->assertSame($doctor->user_id, $log->user_id);
        $this->assertSame($assessment->id, $log->auditable_id);
        $this->assertSame(MedicalAssessment::class, $log->auditable_type);
        $this->assertNotNull($log->ip_address);
        $this->assertNull($log->metadata, 'no clinical content is written to the audit log');
    }

    public function test_the_doctor_approves_and_the_client_is_told_without_clinical_detail(): void
    {
        Notification::fake();
        $doctor = $this->doctor();
        $client = $this->client();
        $assessment = $this->submittedAssessment($client, ['allergies' => 'حساسية سرية']);

        $this->actingAs($doctor->user)->post(route('practitioner.assessments.review', $assessment), ['decision' => 'approved'])
            ->assertRedirect(route('practitioner.assessments.index'));

        $assessment->refresh();
        $this->assertSame(AssessmentStatus::Approved, $assessment->status);
        $this->assertSame($doctor->user_id, $assessment->reviewed_by);
        $this->assertNotNull($assessment->reviewed_at);

        Notification::assertSentTo($client, GenericNotification::class, function (GenericNotification $notification) use ($client) {
            $payload = json_encode($notification->toArray($client), JSON_UNESCAPED_UNICODE);

            return str_contains($payload, 'تمت مراجعة تقييمك الطبي') && ! str_contains($payload, 'حساسية');
        });
        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment.reviewed', 'user_id' => $doctor->user_id]);
    }

    public function test_asking_for_more_information_or_declining_needs_an_explanatory_note(): void
    {
        $doctor = $this->doctor();
        $assessment = $this->submittedAssessment($this->client());

        $this->actingAs($doctor->user)->post(route('practitioner.assessments.review', $assessment), ['decision' => 'needs_info'])
            ->assertSessionHasErrors('notes');
        $this->actingAs($doctor->user)->post(route('practitioner.assessments.review', $assessment), ['decision' => 'not_suitable', 'notes' => ''])
            ->assertSessionHasErrors('notes');

        $this->assertSame(AssessmentStatus::Submitted, $assessment->fresh()->status);

        $this->actingAs($doctor->user)->post(route('practitioner.assessments.review', $assessment), ['decision' => 'needs_info', 'notes' => 'أضف التحاليل'])
            ->assertRedirect();
        $this->assertSame('أضف التحاليل', $assessment->fresh()->reviewer_notes);
    }

    public function test_only_valid_decisions_are_accepted(): void
    {
        $doctor = $this->doctor();
        $assessment = $this->submittedAssessment($this->client());

        $this->actingAs($doctor->user)->post(route('practitioner.assessments.review', $assessment), ['decision' => 'draft'])
            ->assertSessionHasErrors('decision');
    }

    public function test_a_reviewed_assessment_cannot_be_reviewed_again(): void
    {
        $doctor = $this->doctor();
        $assessment = MedicalAssessment::factory()->approved()->create(['user_id' => $this->client()->id]);

        $this->actingAs($doctor->user)->post(route('practitioner.assessments.review', $assessment), ['decision' => 'not_suitable', 'notes' => 'تغيير'])
            ->assertForbidden();

        $this->assertSame(AssessmentStatus::Approved, $assessment->fresh()->status);
    }

    public function test_admin_support_care_team_and_dietitians_cannot_open_an_assessment(): void
    {
        $assessment = $this->submittedAssessment($this->client());

        foreach ([$this->admin(), $this->staff(Role::Support), $this->staff(Role::CareTeam), $this->dietitian()->user, $this->pharmacist(), $this->staff(Role::Delivery)] as $user) {
            $this->actingAs($user)->get(route('practitioner.assessments.show', $assessment))->assertForbidden();
        }

        $this->assertDatabaseMissing('audit_logs', ['action' => 'assessment.viewed']);
    }

    public function test_policy_lets_a_dietitian_see_only_the_assessment_of_a_client_assigned_to_them(): void
    {
        $client = $this->client();
        $assessment = MedicalAssessment::factory()->approved()->create(['user_id' => $client->id]);
        $dietitian = $this->dietitian();
        $other = $this->dietitian();

        $this->assertFalse($dietitian->user->can('view', $assessment));

        WeightPlan::factory()->create(['user_id' => $client->id, 'dietitian_id' => $dietitian->id]);

        $this->assertTrue($dietitian->user->can('view', $assessment));
        $this->assertFalse($other->user->can('view', $assessment));
    }

    public function test_policy_clinical_access_matrix(): void
    {
        $client = $this->client();
        $assessment = $this->submittedAssessment($client);

        $this->assertTrue($client->can('view', $assessment));
        $this->assertTrue($this->doctor()->user->can('view', $assessment));
        $this->assertFalse($this->client()->can('view', $assessment));
        foreach ([Role::Admin, Role::Support, Role::CareTeam, Role::Delivery] as $role) {
            $this->assertFalse($this->staff($role)->can('view', $assessment), "{$role->value} must not view assessments");
        }
    }

    public function test_clients_cannot_reach_the_practitioner_area(): void
    {
        $this->actingAs($this->client())->get(route('practitioner.assessments.index'))->assertForbidden();
    }

    public function test_client_search_needs_three_characters_and_never_lists_everyone(): void
    {
        $doctor = $this->doctor();
        $this->client(['name' => 'سلمى الأحمد', 'phone' => '+966551110001']);
        $this->client(['name' => 'سعود الأحمد', 'phone' => '+966551110002']);

        $this->actingAs($doctor->user)->get(route('practitioner.clients.index'))
            ->assertOk()->assertSee('لا نعرض قائمة كاملة بالعملاء')->assertDontSee('سلمى');

        $this->actingAs($doctor->user)->get(route('practitioner.clients.index', ['q' => 'سل']))
            ->assertSee('اكتب 3 أحرف على الأقل.')->assertDontSee('سلمى');

        $this->actingAs($doctor->user)->get(route('practitioner.clients.index', ['q' => 'الأحمد']))
            ->assertSee('سلمى الأحمد')->assertSee('سعود الأحمد');

        $this->actingAs($doctor->user)->get(route('practitioner.clients.index', ['q' => '0002']))
            ->assertSee('سعود الأحمد')->assertDontSee('سلمى');
    }

    public function test_search_does_not_expose_staff_members_or_full_phone_numbers(): void
    {
        $doctor = $this->doctor();
        $this->staff(Role::Admin, ['name' => 'مدير سري الاسم']);
        $client = $this->client(['name' => 'عميلة ظاهرة', 'phone' => '+966551234567']);

        $this->actingAs($doctor->user)->get(route('practitioner.clients.index', ['q' => 'سري']))->assertDontSee('مدير سري الاسم');

        $this->actingAs($doctor->user)->get(route('practitioner.clients.index', ['q' => 'ظاهرة']))
            ->assertSee('عميلة ظاهرة')
            ->assertDontSee('+966551234567');
    }

    public function test_dietitian_search_only_reaches_clients_assigned_to_them(): void
    {
        $dietitian = $this->dietitian();
        $mine = $this->client(['name' => 'سارة المسندة']);
        $other = $this->client(['name' => 'سارة الأخرى']);
        WeightPlan::factory()->create(['user_id' => $mine->id, 'dietitian_id' => $dietitian->id]);

        $this->actingAs($dietitian->user)->get(route('practitioner.clients.index', ['q' => 'سارة']))
            ->assertSee('سارة المسندة')->assertDontSee('سارة الأخرى');

        $this->actingAs($dietitian->user)->get(route('practitioner.clients.show', $other))->assertForbidden();
        $this->actingAs($dietitian->user)->get(route('practitioner.clients.show', $mine))->assertOk();
    }

    public function test_opening_a_clients_clinical_file_is_audited(): void
    {
        $doctor = $this->doctor();
        $client = $this->approvedClient();

        $this->actingAs($doctor->user)->get(route('practitioner.clients.show', $client))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'client.clinical_viewed', 'user_id' => $doctor->user_id, 'auditable_id' => $client->id, 'auditable_type' => User::class,
        ]);
    }

    public function test_a_clinical_file_cannot_be_opened_for_a_staff_account(): void
    {
        $doctor = $this->doctor();

        $this->actingAs($doctor->user)->get(route('practitioner.clients.show', $this->admin()))->assertForbidden();
    }
}
