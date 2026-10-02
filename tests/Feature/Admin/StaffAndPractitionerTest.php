<?php

namespace Tests\Feature\Admin;

use App\Enums\PractitionerType;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\Practitioner;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAndPractitionerTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->admin();
    }

    private function staffPayload(array $overrides = []): array
    {
        return [
            'name' => 'د. فهد العتيبي',
            'phone' => '0551234000',
            'email' => 'fahad@example.com',
            'password' => 'Str0ngPassword!',
            'role' => 'doctor',
            'name_en' => 'Dr. Fahad',
            'specialty_ar' => 'استشاري غدد صماء',
            'specialty_en' => 'Endocrinology Consultant',
            'license_number' => 'SCFHS-777',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_an_admin_creates_a_doctor_account_with_a_practitioner_profile_that_is_not_yet_verified(): void
    {
        $this->actingAs($this->admin)->post(route('admin.staff.store'), $this->staffPayload(['accepts_instant' => '1']))
            ->assertRedirect(route('admin.staff.index'));

        $user = User::where('phone', '+966551234000')->firstOrFail();
        $this->assertSame(Role::Doctor, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Str0ngPassword!', $user->password));

        $practitioner = $user->practitioner;
        $this->assertSame(PractitionerType::Doctor, $practitioner->type);
        $this->assertSame('SCFHS-777', $practitioner->license_number);
        $this->assertTrue($practitioner->accepts_instant);
        $this->assertNull($practitioner->license_verified_at, 'a new practitioner is hidden until the licence is verified');
        $this->assertFalse($practitioner->isVerified());
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.created', 'user_id' => $this->admin->id]);
    }

    public function test_a_dietitian_does_not_get_instant_consultations(): void
    {
        $this->actingAs($this->admin)->post(route('admin.staff.store'), $this->staffPayload(['role' => 'dietitian', 'accepts_instant' => '1']));

        $practitioner = User::where('phone', '+966551234000')->firstOrFail()->practitioner;
        $this->assertSame(PractitionerType::Dietitian, $practitioner->type);
        $this->assertFalse($practitioner->accepts_instant);
    }

    public function test_pharmacy_staff_must_belong_to_a_pharmacy_and_other_roles_are_detached(): void
    {
        $pharmacy = Pharmacy::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.staff.store'), $this->staffPayload(['role' => 'pharmacy', 'pharmacy_id' => null]))->assertSessionHasErrors('pharmacy_id');

        $this->actingAs($this->admin)->post(route('admin.staff.store'), $this->staffPayload(['role' => 'pharmacy', 'pharmacy_id' => $pharmacy->id]))->assertRedirect();
        $this->assertSame($pharmacy->id, User::where('phone', '+966551234000')->firstOrFail()->pharmacy_id);

        $this->actingAs($this->admin)->post(route('admin.staff.store'), $this->staffPayload(['phone' => '0551234001', 'email' => 'other@example.com', 'role' => 'care_team', 'pharmacy_id' => $pharmacy->id]));
        $this->assertNull(User::where('phone', '+966551234001')->firstOrFail()->pharmacy_id);
    }

    public function test_the_client_role_cannot_be_assigned_through_the_staff_form(): void
    {
        $this->actingAs($this->admin)->post(route('admin.staff.store'), $this->staffPayload(['role' => 'client']))->assertSessionHasErrors('role');
        $this->actingAs($this->admin)->post(route('admin.staff.store'), $this->staffPayload(['role' => 'superuser']))->assertSessionHasErrors('role');
    }

    public function test_staff_input_is_validated(): void
    {
        $existing = $this->client(['phone' => '+966551234000', 'email' => 'fahad@example.com']);
        $post = fn (array $o) => $this->actingAs($this->admin)->post(route('admin.staff.store'), $this->staffPayload($o));

        $post([])->assertSessionHasErrors(['phone', 'email']);
        $post(['phone' => '0559998888', 'email' => 'new@example.com', 'password' => 'short'])->assertSessionHasErrors('password');
        $post(['phone' => '0559998888', 'email' => 'new@example.com', 'password' => ''])->assertSessionHasErrors('password');
        $post(['phone' => 'not-a-number', 'email' => 'new@example.com'])->assertSessionHasErrors('phone');
        $post(['phone' => '0559998888', 'email' => 'new@example.com', 'name' => ''])->assertSessionHasErrors('name');
    }

    public function test_the_same_licence_number_cannot_be_used_twice(): void
    {
        Practitioner::factory()->create(['license_number' => 'SCFHS-777']);

        $this->actingAs($this->admin)->post(route('admin.staff.store'), $this->staffPayload())->assertSessionHasErrors('license_number');
    }

    public function test_editing_staff_keeps_the_password_unless_a_new_one_is_given_and_can_deactivate(): void
    {
        $member = $this->staff(Role::CareTeam);
        $hash = $member->password;

        $this->actingAs($this->admin)->put(route('admin.staff.update', $member), [
            'name' => 'اسم جديد', 'phone' => $member->phone, 'email' => $member->email, 'role' => 'care_team', 'password' => '', 'is_active' => '0',
        ])->assertRedirect(route('admin.staff.index'));

        $member->refresh();
        $this->assertSame('اسم جديد', $member->name);
        $this->assertSame($hash, $member->password);
        $this->assertFalse($member->is_active);

        $this->actingAs($this->admin)->put(route('admin.staff.update', $member), [
            'name' => 'اسم جديد', 'phone' => $member->phone, 'email' => $member->email, 'role' => 'care_team', 'password' => 'NewStr0ngPass!', 'is_active' => '1',
        ]);
        $this->assertTrue(Hash::check('NewStr0ngPass!', $member->fresh()->password));
    }

    public function test_changing_a_role_is_audited(): void
    {
        $member = $this->staff(Role::CareTeam);

        $this->actingAs($this->admin)->put(route('admin.staff.update', $member), [
            'name' => $member->name, 'phone' => $member->phone, 'role' => 'support', 'is_active' => '1',
        ]);

        $this->assertSame(Role::Support, $member->fresh()->role);
        $log = AuditLog::where('action', 'staff.updated')->firstOrFail();
        $this->assertSame(['role' => 'support'], $log->metadata);
    }

    public function test_an_admin_cannot_edit_their_own_account_through_the_staff_screen(): void
    {
        $this->actingAs($this->admin)->put(route('admin.staff.update', $this->admin), [
            'name' => 'x', 'phone' => $this->admin->phone, 'role' => 'client', 'is_active' => '0',
        ])->assertForbidden();

        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertSame(Role::Admin, $this->admin->fresh()->role);
    }

    public function test_clients_do_not_appear_in_or_open_from_the_staff_screens(): void
    {
        $client = $this->client(['name' => 'عميل لا يظهر هنا']);

        $this->actingAs($this->admin)->get(route('admin.staff.index'))->assertDontSee('عميل لا يظهر هنا');
        $this->actingAs($this->admin)->get(route('admin.staff.edit', $client))->assertNotFound();
        $this->actingAs($this->admin)->put(route('admin.staff.update', $client), ['name' => 'x', 'phone' => '0551112233', 'role' => 'admin'])->assertNotFound();
    }

    public function test_the_staff_list_can_be_filtered_by_role(): void
    {
        $this->staff(Role::CareTeam, ['name' => 'موظفة رعاية']);
        $this->staff(Role::Delivery, ['name' => 'مندوب توصيل']);

        $this->actingAs($this->admin)->get(route('admin.staff.index', ['role' => 'care_team']))->assertSee('موظفة رعاية')->assertDontSee('مندوب توصيل');
    }

    // ---- licence verification ------------------------------------------------------------------------------

    public function test_verifying_the_licence_makes_the_practitioner_public_and_bookable(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));
        $practitioner = Practitioner::factory()->alwaysAvailable()->unverified()->create();
        $practitioner->user->update(['name' => 'د. بانتظار التوثيق']);
        $client = $this->client();

        $this->get(route('team'))->assertDontSee('د. بانتظار التوثيق');
        $this->actingAs($client)->post(route('account.appointments.store'), [
            'type' => 'free_intro', 'mode' => 'video', 'starts_at' => now()->addDay()->format('Y-m-d').' 10:00', 'acknowledge' => '1',
        ])->assertSessionHas('error');

        $this->actingAs($this->admin)->post(route('admin.practitioners.verify', $practitioner), ['license_expires_on' => now()->addYear()->toDateString()])
            ->assertSessionHas('status');

        $practitioner->refresh();
        $this->assertNotNull($practitioner->license_verified_at);
        $this->assertSame($this->admin->id, $practitioner->license_verified_by);
        $this->get(route('team'))->assertSee('د. بانتظار التوثيق')->assertSee($practitioner->license_number);
        $this->actingAs($client)->post(route('account.appointments.store'), [
            'type' => 'free_intro', 'mode' => 'video', 'starts_at' => now()->addDay()->format('Y-m-d').' 10:00', 'acknowledge' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'practitioner.license_verified']);
    }

    public function test_verification_needs_a_licence_number(): void
    {
        $practitioner = Practitioner::factory()->unverified()->create(['license_number' => null]);

        $this->actingAs($this->admin)->post(route('admin.practitioners.verify', $practitioner))
            ->assertSessionHas('error', 'أدخل رقم الترخيص قبل التوثيق.');

        $this->assertNull($practitioner->fresh()->license_verified_at);
    }

    public function test_revoking_verification_hides_the_practitioner_immediately(): void
    {
        $practitioner = $this->doctor();
        $practitioner->user->update(['name' => 'د. سيُسحب توثيقه']);

        $this->get(route('team'))->assertSee('د. سيُسحب توثيقه');
        $this->actingAs($this->admin)->post(route('admin.practitioners.revoke', $practitioner))->assertSessionHas('status');

        $this->get(route('team'))->assertDontSee('د. سيُسحب توثيقه');
        $this->assertFalse($practitioner->fresh()->isVerified());
    }

    public function test_an_expired_licence_hides_a_practitioner_even_if_it_was_verified(): void
    {
        $practitioner = $this->doctor(['license_expires_on' => now()->addDay()->toDateString()]);
        $this->assertTrue($practitioner->isVerified());

        $this->travel(3)->days();

        $this->assertFalse($practitioner->fresh()->isVerified());
        $this->assertSame(0, Practitioner::verified()->count());
    }

    public function test_changing_the_licence_number_cancels_the_previous_verification(): void
    {
        $practitioner = $this->doctor(['license_number' => 'SCFHS-100']);

        $this->actingAs($this->admin)->put(route('admin.practitioners.update', $practitioner), [
            'specialty_ar' => 'تخصص', 'license_number' => 'SCFHS-200', 'license_authority' => 'SCFHS', 'is_active' => '1',
        ])->assertRedirect(route('admin.practitioners.index'));

        $practitioner->refresh();
        $this->assertSame('SCFHS-200', $practitioner->license_number);
        $this->assertNull($practitioner->license_verified_at);
    }

    public function test_keeping_the_licence_number_keeps_the_verification(): void
    {
        $practitioner = $this->doctor(['license_number' => 'SCFHS-100']);

        $this->actingAs($this->admin)->put(route('admin.practitioners.update', $practitioner), [
            'specialty_ar' => 'تخصص جديد', 'license_number' => 'SCFHS-100', 'license_authority' => 'SCFHS', 'is_active' => '1', 'bio_ar' => 'نبذة',
        ]);

        $practitioner->refresh();
        $this->assertSame('تخصص جديد', $practitioner->specialty_ar);
        $this->assertNotNull($practitioner->license_verified_at);
    }

    public function test_a_deactivated_practitioner_disappears_from_booking_but_keeps_their_history(): void
    {
        $practitioner = $this->doctor();
        $appointment = Appointment::factory()->create(['practitioner_id' => $practitioner->id]);

        $practitioner->update(['is_active' => false]);

        $this->assertSame(0, Practitioner::verified()->count());
        $this->assertSame($practitioner->id, $appointment->fresh()->practitioner_id);
    }

    public function test_the_practitioner_list_shows_verification_state(): void
    {
        $this->doctor();
        Practitioner::factory()->unverified()->create();

        $this->actingAs($this->admin)->get(route('admin.practitioners.index'))->assertSee('موثّق')->assertSee('بانتظار التوثيق');
    }

    public function test_a_doctor_manages_their_own_weekly_availability(): void
    {
        $doctor = $this->doctor();
        $doctor->availabilities()->delete();

        $this->actingAs($doctor->user)->put(route('practitioner.availability.update'), [
            'slot_minutes' => 30,
            'days' => [0 => [['start' => '09:00', 'end' => '12:00'], ['start' => '16:00', 'end' => '20:00']], 2 => [['start' => '08:00', 'end' => '10:00']]],
        ])->assertRedirect(route('practitioner.availability.edit'));

        $this->assertSame(3, $doctor->availabilities()->count());
        $this->assertSame([30], $doctor->availabilities()->pluck('slot_minutes')->unique()->values()->all());

        $this->actingAs($doctor->user)->get(route('practitioner.availability.edit'))->assertOk()->assertSee('value="16:00"', false);
    }

    public function test_availability_input_is_validated_and_only_doctors_can_set_it(): void
    {
        $doctor = $this->doctor();

        $this->actingAs($doctor->user)->put(route('practitioner.availability.update'), ['slot_minutes' => 7])->assertSessionHasErrors('slot_minutes');
        $this->actingAs($doctor->user)->put(route('practitioner.availability.update'), ['slot_minutes' => 20, 'days' => [0 => [['start' => '12:00', 'end' => '09:00']]]])->assertSessionHasErrors('days.0.0.end');
        $this->actingAs($doctor->user)->put(route('practitioner.availability.update'), ['slot_minutes' => 20, 'days' => [0 => [['start' => '09:00', 'end' => '']]]])->assertSessionHasErrors('days.0.0.end');

        $this->actingAs($this->dietitian()->user)->put(route('practitioner.availability.update'), ['slot_minutes' => 20])->assertForbidden();
    }

    public function test_the_pharmacies_crud_and_licence_uniqueness(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pharmacies.store'), ['name' => 'صيدلية النور', 'license_number' => 'PH-1', 'city' => 'جدة', 'is_active' => '1'])->assertRedirect(route('admin.pharmacies.index'));
        $pharmacy = Pharmacy::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.pharmacies.store'), ['name' => 'مكررة', 'license_number' => 'PH-1', 'city' => 'جدة'])->assertSessionHasErrors('license_number');
        $this->actingAs($this->admin)->put(route('admin.pharmacies.update', $pharmacy), ['name' => 'صيدلية النور الجديدة', 'license_number' => 'PH-1', 'city' => 'جدة'])->assertRedirect();

        $this->assertSame('صيدلية النور الجديدة', $pharmacy->fresh()->name);
        $this->assertFalse($pharmacy->fresh()->is_active, 'unchecked checkbox means inactive');
    }
}
