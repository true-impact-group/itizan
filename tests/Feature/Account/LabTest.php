<?php

namespace Tests\Feature\Account;

use App\Enums\LabStatus;
use App\Models\Attachment;
use App\Models\LabRequest;
use App\Notifications\GenericNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LabTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_a_doctor_requests_a_lab_test_and_the_client_is_told(): void
    {
        Notification::fake();
        $doctor = $this->doctor();
        $client = $this->approvedClient();

        $this->actingAs($doctor->user)->post(route('practitioner.labs.store', $client), [
            'name' => 'سكر تراكمي', 'instructions' => 'صيام 8 ساعات', 'due_on' => now()->addWeek()->toDateString(),
        ])->assertRedirect(route('practitioner.clients.show', $client));

        $lab = LabRequest::firstOrFail();
        $this->assertSame(LabStatus::Requested, $lab->status);
        $this->assertSame($doctor->id, $lab->practitioner_id);
        Notification::assertSentTo($client, GenericNotification::class);
        $this->actingAs($client)->get(route('account.labs.index'))->assertSee('سكر تراكمي')->assertSee('صيام 8 ساعات');
    }

    public function test_lab_request_input_is_validated_and_only_verified_doctors_may_request(): void
    {
        $doctor = $this->doctor();
        $client = $this->approvedClient();

        $this->actingAs($doctor->user)->post(route('practitioner.labs.store', $client), ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($doctor->user)->post(route('practitioner.labs.store', $client), ['name' => 'تحليل', 'due_on' => now()->subDay()->toDateString()])->assertSessionHasErrors('due_on');

        $unverified = $this->doctor(['license_verified_at' => null]);
        $this->actingAs($unverified->user)->post(route('practitioner.labs.store', $client), ['name' => 'تحليل'])
            ->assertSessionHas('error', 'فقط الطبيب الموثّق يمكنه طلب التحاليل.');

        $this->actingAs($this->dietitian()->user)->post(route('practitioner.labs.store', $client), ['name' => 'تحليل'])->assertForbidden();
    }

    public function test_the_client_uploads_results_and_the_request_moves_to_uploaded(): void
    {
        $client = $this->client();
        $lab = LabRequest::factory()->create(['user_id' => $client->id]);

        $this->actingAs($client)->post(route('account.labs.upload', $lab), [
            'files' => [UploadedFile::fake()->create('hba1c.pdf', 200, 'application/pdf'), UploadedFile::fake()->image('page2.png')],
        ])->assertRedirect(route('account.labs.index'));

        $lab->refresh();
        $this->assertSame(LabStatus::Uploaded, $lab->status);
        $this->assertNotNull($lab->result_uploaded_at);
        $this->assertSame(2, Attachment::where('attachable_type', $lab->getMorphClass())->where('attachable_id', $lab->id)->count());
        foreach (Attachment::all() as $file) {
            Storage::disk('local')->assertExists($file->path);
        }
    }

    public function test_upload_validation_rejects_missing_dangerous_or_oversized_files(): void
    {
        $client = $this->client();
        $lab = LabRequest::factory()->create(['user_id' => $client->id]);
        $upload = fn (array $files) => $this->actingAs($client)->post(route('account.labs.upload', $lab), ['files' => $files]);

        $upload([])->assertSessionHasErrors('files');
        $upload([UploadedFile::fake()->create('shell.php', 5, 'application/x-php')])->assertSessionHasErrors('files.0');
        $upload([UploadedFile::fake()->create('big.pdf', 9000, 'application/pdf')])->assertSessionHasErrors('files.0');
        $upload(array_fill(0, 6, UploadedFile::fake()->image('a.jpg')))->assertSessionHasErrors('files');

        $this->assertSame(LabStatus::Requested, $lab->fresh()->status);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_a_client_cannot_upload_to_a_test_that_belongs_to_someone_else(): void
    {
        $lab = LabRequest::factory()->create();

        $this->actingAs($this->client())->post(route('account.labs.upload', $lab), ['files' => [UploadedFile::fake()->image('a.jpg')]])->assertForbidden();
    }

    public function test_the_doctor_reviews_the_result_and_the_client_reads_the_note(): void
    {
        Notification::fake();
        $doctor = $this->doctor();
        $client = $this->client();
        $lab = LabRequest::factory()->uploaded()->create(['user_id' => $client->id]);

        $this->actingAs($doctor->user)->get(route('practitioner.labs.index'))->assertOk()->assertSee($client->name);
        $this->actingAs($doctor->user)->post(route('practitioner.labs.review', $lab), ['notes' => 'النتيجة ضمن الطبيعي'])->assertRedirect(route('practitioner.labs.index'));

        $lab->refresh();
        $this->assertSame(LabStatus::Reviewed, $lab->status);
        $this->assertNotNull($lab->reviewed_at);
        Notification::assertSentTo($client, GenericNotification::class);
        $this->actingAs($client)->get(route('account.labs.index'))->assertSee('النتيجة ضمن الطبيعي');
    }

    public function test_a_test_with_no_uploaded_result_cannot_be_reviewed(): void
    {
        $lab = LabRequest::factory()->create();

        $this->actingAs($this->doctor()->user)->post(route('practitioner.labs.review', $lab), ['notes' => 'x'])->assertForbidden();
    }

    public function test_a_reviewed_test_is_closed_to_further_uploads(): void
    {
        $client = $this->client();
        $lab = LabRequest::factory()->create(['user_id' => $client->id, 'status' => LabStatus::Reviewed]);

        $this->actingAs($client)->post(route('account.labs.upload', $lab), ['files' => [UploadedFile::fake()->image('a.jpg')]])->assertForbidden();
    }

    public function test_review_notes_are_encrypted_at_rest(): void
    {
        $lab = LabRequest::factory()->uploaded()->create();
        $this->actingAs($this->doctor()->user)->post(route('practitioner.labs.review', $lab), ['notes' => 'ملاحظة طبية سرية']);

        $this->assertStringNotContainsString('سرية', (string) DB::table('lab_requests')->value('reviewer_notes'));
    }

    public function test_the_lab_list_renders_with_several_requests_without_extra_queries_per_row(): void
    {
        $client = $this->client();
        LabRequest::factory()->count(6)->create(['user_id' => $client->id]);

        DB::enableQueryLog();
        $this->actingAs($client)->get(route('account.labs.index'))->assertOk();
        $queries = count(DB::getQueryLog());

        $this->assertLessThan(15, $queries, "N+1 suspected: {$queries} queries for 6 rows");
    }
}
