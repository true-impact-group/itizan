<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\AssessmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\MedicalAssessment;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WeightPlan;
use App\Services\ClientJourney;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * One client walks the whole brief, step by step, through the real HTTP layer while the doctor, dietitian,
 * pharmacy and delivery partner act in between. If any hand-off between roles breaks, this fails.
 */
class CustomerJourneyTest extends TestCase
{
    public function test_a_client_completes_the_entire_journey_from_discovery_to_delivery_and_renewal_management(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));
        $this->seedCatalog();

        $doctor = $this->doctor();
        $dietitian = $this->dietitian();
        $pharmacy = Pharmacy::factory()->create();
        $pharmacist = $this->pharmacist($pharmacy);
        $driver = $this->staff(Role::Delivery);
        $care = $this->staff(Role::CareTeam);
        $medication = Product::factory()->medication()->create(['name_ar' => 'دواء الرحلة']);

        // 1. Discover: the public pages, then the call to action.
        $this->get(route('home'))->assertOk()->assertSee('ابدأ باستشارة مجانية');
        $this->get(route('pricing'))->assertOk()->assertSee('999');
        $this->get(route('team'))->assertSee($doctor->license_number);

        // 2 + 3. Choose the free consultation, register with a mobile number, land on the booking step.
        $this->get(route('free-consultation'))->assertRedirect(route('register'));
        $this->post(route('register'), [
            'name' => 'منى العلي', 'phone' => '0551234567', 'email' => 'mona@example.com',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
            'gender' => 'male', 'birth_date' => '1990-04-01', 'city' => 'الرياض', 'terms' => '1',
        ])->assertRedirect(route('account.appointments.create', ['type' => 'free_intro']));

        $client = User::where('phone', '+966551234567')->firstOrFail();
        $this->assertSame('ابدأ باستشارتك المجانية', (new ClientJourney)->nextStep($client)['title']);

        // 4. Book and attend the free consultation (scope shown first, then the doctor finishes the session).
        $this->get(route('account.appointments.create', ['type' => 'free_intro']))->assertOk()->assertSee('FTMS');
        $slot = now()->addDay()->format('Y-m-d').' 10:00';
        $this->post(route('account.appointments.store'), ['type' => 'free_intro', 'mode' => 'video', 'starts_at' => $slot, 'acknowledge' => '1'])->assertRedirect();

        $intro = Appointment::firstOrFail();
        $this->assertSame(AppointmentStatus::Confirmed, $intro->status);
        $this->assertSame('موعدك القادم', (new ClientJourney)->nextStep($client)['title']);

        $this->travelTo($intro->scheduled_at->copy()->subMinutes(5));
        $this->get(route('account.appointments.show', $intro))->assertSee($intro->meeting_url);

        $this->actingAs($doctor->user)->post(route('practitioner.appointments.complete', $intro), ['outcome' => 'completed', 'notes' => 'استشارة تعريفية'])->assertRedirect();
        $this->assertSame('اختر الباقة المناسبة', (new ClientJourney)->nextStep($client)['title']);

        // 6. Subscribe (six months, with automatic renewal as a separate consent) and pay.
        $package = Package::where('slug', 'six-months')->firstOrFail();
        $this->actingAs($client)->get(route('account.checkout.show', $package))->assertOk()->assertSee('130.30 ريال');
        $this->post(route('account.checkout.store', $package), ['payment_method' => 'mada', 'auto_renew' => '1', 'accept_terms' => '1'])->assertRedirect();

        $payment = Payment::firstOrFail();
        $this->post(route('checkout.sandbox.complete', $payment), ['outcome' => 'paid'])->assertRedirect(route('account.subscription.show'));
        $subscription = Subscription::firstOrFail();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->auto_renew);
        $this->assertSame('أكمل تقييمك الطبي', (new ClientJourney)->nextStep($client)['title']);

        // 5. Medical assessment: submit, the doctor reviews and approves.
        $this->actingAs($client)->put(route('account.assessment.update'), [
            'action' => 'submit', 'height_cm' => '172', 'weight_kg' => '98', 'waist_cm' => '108', 'target_weight_kg' => '82',
            'chronic_conditions' => 'لا يوجد', 'current_medications' => 'لا يوجد', 'allergies' => 'لا يوجد', 'goals' => 'وزن صحي ومستدام',
            'safety_answers' => array_fill_keys(array_keys(config('itizan.safety_questions')), 'no'), 'medical_consent' => '1',
        ])->assertRedirect(route('account.assessment.show'));
        $assessment = MedicalAssessment::firstOrFail();
        $this->assertSame(AssessmentStatus::Submitted, $assessment->status);
        $this->assertSame('تقييمك عند الطبيب', (new ClientJourney)->nextStep($client)['title']);

        $this->actingAs($doctor->user)->get(route('practitioner.assessments.show', $assessment))->assertOk()->assertSee('وزن صحي ومستدام');
        $this->post(route('practitioner.assessments.review', $assessment), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('فريقك يُعدّ خطتك', (new ClientJourney)->nextStep($client)['title']);

        // 7. The doctor prepares the plan (with a dietitian); the client follows it and records progress.
        $this->post(route('practitioner.plans.store', $client), [
            'target_weight_kg' => '82', 'target_waist_cm' => '95', 'doctor_notes' => 'نبدأ بخطوات صغيرة', 'next_steps' => ['سجّل وزنك أسبوعيًا'],
            'review_on' => now()->addMonth()->toDateString(), 'dietitian_id' => $dietitian->id,
        ])->assertRedirect();
        $this->actingAs($dietitian->user)->put(route('practitioner.plans.update', WeightPlan::firstOrFail()), ['nutrition_guidance' => 'وجبات متوازنة'])->assertRedirect();

        $this->actingAs($client)->get(route('account.plan.show'))->assertOk()->assertSee('نبدأ بخطوات صغيرة')->assertSee('وجبات متوازنة')->assertSee('سجّل وزنك أسبوعيًا');
        $this->post(route('account.progress.store'), ['measured_on' => today()->toDateString(), 'weight_kg' => '96.5', 'waist_cm' => '106'])->assertRedirect();
        $this->get(route('account.dashboard'))->assertOk()->assertSee('خطتك وتقدمك');

        // 8. Treatment: the doctor prescribes; the client logs the dose after answering the pre-dose question.
        $this->actingAs($doctor->user)->post(route('practitioner.prescriptions.store', $client), [
            'medication_name' => 'دواء الرحلة', 'dose' => '0.25 ملغ', 'interval_days' => '7', 'dose_time' => '09:00', 'starts_on' => today()->toDateString(),
            'product_id' => $medication->id, 'pre_dose_questions' => [['text' => 'هل تشعر بغثيان شديد؟', 'alert_on' => 'yes']],
        ])->assertRedirect();
        $rx = Prescription::firstOrFail();
        $this->assertSame(PrescriptionStatus::Active, $rx->status);

        $this->travelTo($rx->next_dose_at);
        $this->actingAs($client)->get(route('account.medications.show', $rx))->assertSee('هل تشعر بغثيان شديد؟');
        $this->post(route('account.medications.doses.store', $rx), ['status' => 'taken', 'answers' => ['no']])->assertSessionHas('status');
        $this->assertDatabaseCount('dose_logs', 1);
        $this->assertTrue($rx->fresh()->next_dose_at->greaterThan(now()->addDays(6)));

        $this->post(route('account.side-effects.store'), ['symptom' => 'غثيان خفيف', 'severity' => 'mild', 'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'), 'prescription_id' => $rx->id])->assertRedirect();
        $this->post(route('account.contact-requests.store'), ['target' => 'care_team', 'reason' => 'سؤال عن موعد الجرعة القادمة'])->assertRedirect();
        $this->actingAs($care)->get(route('care.side-effects.index'))->assertOk()->assertSee('غثيان خفيف');

        // 9. Order the medication: the pharmacy quotes, the client approves, the pharmacy prepares, the driver delivers.
        $this->actingAs($client)->post(route('account.orders.store'), [
            'items' => [['product_id' => $medication->id, 'quantity' => 1]], 'prescription_id' => $rx->id,
            'delivery_city' => 'الرياض', 'delivery_address' => 'حي الملقا، شارع الأمير محمد بن سلمان',
        ])->assertRedirect();
        $order = Order::with('items')->firstOrFail();
        $this->assertSame(OrderStatus::Requested, $order->status);

        $this->actingAs($pharmacist)->post(route('pharmacy.orders.quote', $order), [
            'lines' => [$order->items->first()->id => ['available' => '1', 'unit_price' => '450']], 'eta_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();
        $order->refresh();
        $this->assertSame(OrderStatus::Quoted, $order->status);
        $this->assertSame(45000, $order->total_halalas, 'Riyadh delivery is free');

        $this->actingAs($client)->get(route('account.orders.show', $order))->assertSee('راجع السعر ووافق عليه');
        $this->post(route('account.orders.approve', $order), ['total_halalas' => $order->total_halalas, 'agree' => '1'])->assertRedirect();

        $this->actingAs($pharmacist)->post(route('pharmacy.orders.advance', $order), ['to' => 'preparing'])->assertRedirect();
        $this->actingAs($driver)->get(route('delivery.orders.index'))->assertOk()->assertSee('حي الملقا')->assertDontSee('دواء الرحلة');
        $this->post(route('delivery.orders.advance', $order), ['to' => 'out_for_delivery'])->assertRedirect();
        $this->post(route('delivery.orders.advance', $order), ['to' => 'delivered'])->assertRedirect();

        $this->actingAs($client)->get(route('account.orders.show', $order))->assertSee('تم تسليم طلبك')->assertSee('تحديثات الطلب');
        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);

        // A follow-up consultation uses one included visit.
        $this->post(route('account.appointments.store'), [
            'type' => 'follow_up', 'mode' => 'audio', 'starts_at' => now()->addDay()->format('Y-m-d').' 11:00', 'acknowledge' => '1',
        ])->assertRedirect();
        $this->assertSame(1, $subscription->fresh()->followups_used);

        // 10. Manage the subscription: receipt, then turn automatic renewal off.
        $this->get(route('account.subscription.show'))->assertSee($payment->fresh()->invoice_number);
        $this->get(route('account.invoices.show', $payment))->assertOk()->assertSee('إيصال دفع');
        $this->delete(route('account.subscription.auto-renew.disable'))->assertSessionHas('status');
        $this->assertFalse($subscription->fresh()->auto_renew);
        $this->assertTrue($subscription->fresh()->isCurrent(), 'the paid period keeps running');

        // The journey is complete and the client is told so.
        $steps = (new ClientJourney)->steps($client);
        $this->assertSame([], array_column(array_filter($steps, fn ($s) => ! $s['done']), 'key'), 'every one of the ten steps is done');
        $this->get(route('account.dashboard'))->assertOk()->assertSee('رحلتك في اتزان');

        // Hand-offs left the right trail.
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertGreaterThanOrEqual(6, $client->notifications()->count(), 'the client was kept informed along the way');
        $this->assertTrue(AuditLog::where('user_id', $doctor->user_id)->where('action', 'assessment.viewed')->exists());
        $this->assertTrue(AuditLog::where('action', 'prescription.created')->exists());
        foreach ($client->notifications as $notification) {
            $this->assertStringNotContainsString('دواء الرحلة', json_encode($notification->data, JSON_UNESCAPED_UNICODE), 'no medication names in notifications');
        }

        // Privacy held through the whole journey: admin sees the account but none of the clinical data.
        $this->actingAs($this->admin())->get(route('admin.clients.show', $client))->assertOk()->assertDontSee('وزن صحي ومستدام')->assertDontSee('دواء الرحلة');
    }
}
