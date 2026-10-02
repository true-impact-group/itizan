<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->client();
    }

    private function open(array $overrides = [])
    {
        return $this->actingAs($this->client)->post(route('account.tickets.store'), [
            'type' => 'support', 'subject' => 'مشكلة في الفاتورة', 'body' => 'لم تصلني فاتورة اشتراكي الأخير.', ...$overrides,
        ]);
    }

    public function test_a_client_opens_a_support_request_and_support_staff_are_alerted(): void
    {
        Notification::fake();
        $support = $this->staff(Role::Support);

        $this->open()->assertRedirect(route('account.tickets.show', Ticket::firstOrFail()));

        $ticket = Ticket::firstOrFail();
        $this->assertSame(TicketType::Support, $ticket->type);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        Notification::assertSentTo($support, GenericNotification::class);
    }

    public function test_a_complaint_is_tracked_with_its_own_type_and_alert(): void
    {
        Notification::fake();
        $support = $this->staff(Role::Support);

        $this->open(['type' => 'complaint', 'subject' => 'تأخر التوصيل']);

        $this->assertSame(TicketType::Complaint, Ticket::firstOrFail()->type);
        Notification::assertSentTo($support, GenericNotification::class, fn (GenericNotification $n) => str_contains(json_encode($n->toArray(new User), JSON_UNESCAPED_UNICODE), 'شكوى جديدة'));
    }

    public function test_ticket_input_is_validated(): void
    {
        $this->open(['subject' => 'ab'])->assertSessionHasErrors('subject');
        $this->open(['body' => 'قصير'])->assertSessionHasErrors('body');
        $this->open(['type' => 'sales'])->assertSessionHasErrors('type');
        $this->open(['subject' => str_repeat('س', 151)])->assertSessionHasErrors('subject');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_support_replies_and_the_client_is_notified_while_internal_notes_stay_hidden(): void
    {
        Notification::fake();
        $support = $this->staff(Role::Support);
        $ticket = Ticket::factory()->create(['user_id' => $this->client->id]);

        $this->actingAs($support)->post(route('admin.tickets.reply', $ticket), ['body' => 'نعمل على حل مشكلتك'])->assertSessionHas('status');
        $this->actingAs($support)->post(route('admin.tickets.reply', $ticket), ['body' => 'ملاحظة داخلية سرية للفريق', 'internal' => '1']);

        $ticket->refresh();
        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertSame($support->id, $ticket->assigned_to);
        Notification::assertSentToTimes($this->client, GenericNotification::class, 1);

        $this->actingAs($this->client)->get(route('account.tickets.show', $ticket))
            ->assertSee('نعمل على حل مشكلتك')->assertDontSee('ملاحظة داخلية سرية للفريق');
        $this->actingAs($support)->get(route('admin.tickets.show', $ticket))->assertSee('ملاحظة داخلية سرية للفريق')->assertSee('ملاحظة داخلية');
    }

    public function test_a_client_cannot_post_an_internal_note(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->client->id]);

        $this->actingAs($this->client)->post(route('account.tickets.reply', $ticket), ['body' => 'ردي', 'internal' => '1', 'is_internal' => true]);

        $this->assertFalse(TicketReply::firstOrFail()->is_internal);
    }

    public function test_a_client_reply_reopens_a_resolved_ticket(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->client->id, 'status' => TicketStatus::Resolved, 'resolved_at' => now()]);

        $this->actingAs($this->client)->post(route('account.tickets.reply', $ticket), ['body' => 'المشكلة لم تُحل بعد'])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_a_closed_ticket_accepts_no_replies_from_anyone(): void
    {
        $ticket = Ticket::factory()->create(['user_id' => $this->client->id, 'status' => TicketStatus::Closed]);

        $this->actingAs($this->client)->post(route('account.tickets.reply', $ticket), ['body' => 'هل من جديد؟'])->assertSessionHas('error', 'هذه التذكرة مغلقة. افتح طلبًا جديدًا.');
        $this->actingAs($this->staff(Role::Support))->post(route('admin.tickets.reply', $ticket), ['body' => 'رد'])->assertSessionHas('error');

        $this->assertDatabaseCount('ticket_replies', 0);
    }

    public function test_support_updates_the_status_and_resolution_time_is_recorded(): void
    {
        $support = $this->staff(Role::Support);
        $ticket = Ticket::factory()->create(['user_id' => $this->client->id]);

        $this->actingAs($support)->put(route('admin.tickets.update', $ticket), ['status' => 'resolved'])->assertSessionHas('status');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame($support->id, $ticket->assigned_to);

        $this->actingAs($support)->put(route('admin.tickets.update', $ticket), ['status' => 'open']);
        $this->assertNull($ticket->fresh()->resolved_at);
        $this->actingAs($support)->put(route('admin.tickets.update', $ticket), ['status' => 'nonsense'])->assertSessionHasErrors('status');
    }

    public function test_the_ticket_queue_lists_open_work_by_default_and_filters(): void
    {
        $support = $this->staff(Role::Support);
        Ticket::factory()->create(['subject' => 'مفتوحة الآن']);
        Ticket::factory()->create(['subject' => 'منتهية', 'status' => TicketStatus::Resolved]);
        Ticket::factory()->create(['subject' => 'شكوى جارية', 'type' => TicketType::Complaint]);

        $this->actingAs($support)->get(route('admin.tickets.index'))->assertSee('مفتوحة الآن')->assertSee('شكوى جارية')->assertDontSee('منتهية');
        $this->actingAs($support)->get(route('admin.tickets.index', ['status' => 'resolved']))->assertSee('منتهية')->assertDontSee('مفتوحة الآن');
        $this->actingAs($support)->get(route('admin.tickets.index', ['type' => 'complaint']))->assertSee('شكوى جارية')->assertDontSee('مفتوحة الآن');
    }

    public function test_a_client_sees_only_their_own_tickets(): void
    {
        Ticket::factory()->create(['user_id' => $this->client->id, 'subject' => 'طلبي أنا']);
        $other = Ticket::factory()->create(['subject' => 'طلب شخص آخر']);

        $this->actingAs($this->client)->get(route('account.tickets.index'))->assertSee('طلبي أنا')->assertDontSee('طلب شخص آخر');
        $this->actingAs($this->client)->get(route('account.tickets.show', $other))->assertForbidden();
    }

    public function test_clinical_roles_cannot_read_support_tickets(): void
    {
        $ticket = Ticket::factory()->create();

        foreach ([$this->doctor()->user, $this->staff(Role::CareTeam), $this->pharmacist()] as $user) {
            $this->actingAs($user)->get(route('admin.tickets.show', $ticket))->assertForbidden();
        }
    }

    public function test_the_ticket_body_is_stored_encrypted(): void
    {
        $this->open(['body' => 'نص شكوى سري جدًا يحتوي معلومات شخصية']);

        $this->assertStringNotContainsString('سري', (string) DB::table('tickets')->value('body'));
    }

    public function test_the_complaints_form_points_to_the_policy_and_the_contact_page_to_whatsapp(): void
    {
        $this->actingAs($this->client)->get(route('account.tickets.index'))->assertSee('واتساب');
    }
}
