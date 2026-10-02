<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\MedicalAssessment;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    /** @return list<string> routes only an administrator may open */
    private function adminOnly(): array
    {
        return ['admin.payments.index', 'admin.packages.index', 'admin.practitioners.index', 'admin.staff.index', 'admin.staff.create',
            'admin.pages.index', 'admin.faqs.index', 'admin.faqs.create', 'admin.products.index', 'admin.products.create',
            'admin.pharmacies.index', 'admin.pharmacies.create', 'admin.reports', 'admin.audit-logs'];
    }

    /** @return list<string> routes open to administrators and support */
    private function operational(): array
    {
        return ['admin.dashboard', 'admin.clients.index', 'admin.subscriptions.index', 'admin.appointments.index', 'admin.operations', 'admin.tickets.index'];
    }

    public function test_an_administrator_opens_every_admin_screen(): void
    {
        $admin = $this->admin();

        foreach ([...$this->operational(), ...$this->adminOnly()] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_support_staff_open_only_the_operational_screens(): void
    {
        $support = $this->staff(Role::Support);

        foreach ($this->operational() as $route) {
            $this->actingAs($support)->get(route($route))->assertOk();
        }
        foreach ($this->adminOnly() as $route) {
            $this->actingAs($support)->get(route($route))->assertForbidden();
        }
    }

    public function test_support_cannot_change_money_roles_or_content_even_by_posting_directly(): void
    {
        $support = $this->staff(Role::Support);
        $payment = Payment::factory()->paid()->create();
        $client = $this->client();

        $this->actingAs($support)->post(route('admin.payments.refund', $payment))->assertForbidden();
        $this->actingAs($support)->post(route('admin.staff.store'), ['name' => 'مخترق', 'phone' => '0551112222', 'role' => 'admin', 'password' => 'Secret123456'])->assertForbidden();
        $this->actingAs($support)->post(route('admin.clients.toggle-active', $client))->assertForbidden();

        $this->assertDatabaseMissing('users', ['name' => 'مخترق']);
        $this->assertTrue($client->fresh()->is_active);
    }

    public function test_no_other_role_reaches_the_admin_area(): void
    {
        foreach ([$this->client(), $this->doctor()->user, $this->dietitian()->user, $this->staff(Role::CareTeam), $this->pharmacist(), $this->staff(Role::Delivery)] as $user) {
            $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.audit-logs'))->assertForbidden();
        }
    }

    public function test_support_dashboard_hides_revenue_while_admin_sees_it(): void
    {
        Payment::factory()->paid()->create(['amount_halalas' => 123400, 'paid_at' => now()]);

        $this->actingAs($this->staff(Role::Support))->get(route('admin.dashboard'))->assertOk()->assertDontSee('الإيرادات')->assertDontSee('1,234');
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertSee('الإيرادات')->assertSee('1,234 ريال');
    }

    public function test_the_client_account_page_for_admins_contains_no_clinical_data(): void
    {
        $client = $this->client(['name' => 'منى']);
        MedicalAssessment::factory()->create(['user_id' => $client->id, 'goals' => 'هدف طبي سري', 'allergies' => 'حساسية سرية']);
        Prescription::factory()->create(['user_id' => $client->id, 'medication_name' => 'دواء سري']);

        foreach ([$this->admin(), $this->staff(Role::Support)] as $staff) {
            $this->actingAs($staff)->get(route('admin.clients.show', $client))
                ->assertOk()
                ->assertSee('منى')
                ->assertSee('لا تتضمن أي معلومات طبية')
                ->assertDontSee('هدف طبي سري')
                ->assertDontSee('حساسية سرية')
                ->assertDontSee('دواء سري');
        }
    }

    public function test_admin_client_search_matches_name_email_and_phone_and_masks_numbers_in_the_list(): void
    {
        $this->client(['name' => 'ريم الخالد', 'phone' => '+966551110011', 'email' => 'reem@example.com']);
        $this->client(['name' => 'سعد الفهد', 'phone' => '+966552220022']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.clients.index', ['q' => 'ريم']))->assertSee('ريم الخالد')->assertDontSee('سعد الفهد');
        $this->actingAs($admin)->get(route('admin.clients.index', ['q' => 'reem@']))->assertSee('ريم الخالد');
        $this->actingAs($admin)->get(route('admin.clients.index', ['q' => '0022']))->assertSee('سعد الفهد')->assertDontSee('ريم الخالد');
        $this->actingAs($admin)->get(route('admin.clients.index'))->assertDontSee('+966551110011');
    }

    public function test_admin_client_list_excludes_staff_accounts(): void
    {
        $this->staff(Role::Admin, ['name' => 'موظف ظاهر فقط في الموظفين']);

        $this->actingAs($this->admin())->get(route('admin.clients.index'))->assertDontSee('موظف ظاهر فقط في الموظفين');
        $this->actingAs($this->admin())->get(route('admin.clients.show', User::where('role', 'admin')->first()))->assertNotFound();
    }

    public function test_admin_can_suspend_and_reactivate_a_client_and_it_is_audited(): void
    {
        $client = $this->client();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.clients.toggle-active', $client))->assertSessionHas('status', 'تم إيقاف الحساب.');
        $this->assertFalse($client->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.deactivated', 'user_id' => $admin->id]);

        $this->actingAs($admin)->post(route('admin.clients.toggle-active', $client))->assertSessionHas('status', 'تم تفعيل الحساب.');
        $this->assertTrue($client->fresh()->is_active);
    }

    public function test_the_audit_log_page_filters_by_action_and_shows_who_did_what(): void
    {
        $doctor = $this->doctor();
        $this->actingAs($doctor->user)->get(route('practitioner.clients.show', $this->approvedClient()));
        auth()->logout();

        $this->actingAs($this->admin())->get(route('admin.audit-logs', ['action' => 'client.']))
            ->assertOk()->assertSee('client.clinical_viewed')->assertSee($doctor->user->name);
        $this->actingAs($this->admin())->get(route('admin.audit-logs', ['action' => 'assessment.']))->assertDontSee('client.clinical_viewed');
    }

    public function test_the_audit_log_cannot_be_edited_or_deleted_through_the_app(): void
    {
        $log = Audit::log('test.action');

        $this->assertNull(AuditLog::UPDATED_AT);
        $this->assertFalse(Schema::hasColumn('audit_logs', 'deleted_at'));
        foreach (['put', 'patch', 'delete'] as $verb) {
            $this->actingAs($this->admin())->{$verb}('/admin/audit-logs/'.$log->id)->assertStatus(404);
        }
    }
}
