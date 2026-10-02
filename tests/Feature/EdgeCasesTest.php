<?php

namespace Tests\Feature;

use App\Actions\Appointments\BookAppointment;
use App\Actions\Assessment\ReviewAssessment;
use App\Actions\Consent\RecordConsent;
use App\Actions\Labs\UploadLabResult;
use App\Actions\Medication\LogDose;
use App\Enums\AppointmentMode;
use App\Enums\AppointmentType;
use App\Enums\AssessmentStatus;
use App\Enums\ConsentType;
use App\Enums\ContactRequestStatus;
use App\Enums\DoseStatus;
use App\Enums\LabStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Appointment;
use App\Models\ContactRequest;
use App\Models\DoseLog;
use App\Models\LabRequest;
use App\Models\MedicalAssessment;
use App\Models\Order;
use App\Models\Package;
use App\Models\Pharmacy;
use App\Models\Practitioner;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\SideEffectReport;
use App\Models\Ticket;
use App\Models\WeightPlan;
use App\Notifications\GenericNotification;
use App\Notifications\WelcomeNotification;
use App\Services\PackageComparison;
use App\Services\SlotFinder;
use App\Support\Audit;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Race-condition fallbacks, action-level guards, policies, enums and plumbing that the HTTP tests do not reach. */
class EdgeCasesTest extends TestCase
{
    // ---- races ------------------------------------------------------------------------------

    public function test_if_two_bookings_race_past_the_slot_check_the_database_constraint_still_stops_the_second(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 08:00:00'));
        $doctor = $this->doctor();
        $taken = Carbon::parse('2026-10-06 10:00:00');
        Appointment::factory()->create(['practitioner_id' => $doctor->id, 'scheduled_at' => $taken]);

        // A stale slot finder that still believes the slot is free (what a concurrent request would see).
        $this->app->bind(SlotFinder::class, fn () => new class extends SlotFinder
        {
            public function practitionerFor(CarbonInterface $startsAt, ?int $practitionerId = null): ?Practitioner
            {
                return Practitioner::query()->first();
            }
        });

        try {
            app(BookAppointment::class)->handle($this->client(), [
                'type' => AppointmentType::FreeIntro, 'mode' => AppointmentMode::Video, 'starts_at' => $taken, 'instant' => false,
            ]);
            $this->fail('the second booking must not succeed');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('هذا الموعد لم يعد متاحًا، اختر موعدًا آخر.', $e->getMessage());
        }

        $this->assertSame(1, Appointment::count());
    }

    public function test_a_dose_that_was_already_logged_behind_our_back_is_reported_not_crashed(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
        $client = $this->client();
        $rx = Prescription::factory()->due()->create(['user_id' => $client->id]);
        // Another request logged the same scheduled dose but has not advanced the schedule yet.
        DoseLog::create(['prescription_id' => $rx->id, 'user_id' => $client->id, 'scheduled_for' => $rx->next_dose_at, 'status' => 'taken', 'logged_at' => now()]);

        try {
            app(LogDose::class)->handle($client, $rx, DoseStatus::Taken);
            $this->fail('the duplicate must be refused');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('سبق تسجيل هذه الجرعة.', $e->getMessage());
        }
        $this->assertSame(1, DoseLog::count());
    }

    // ---- action-level guards (the UI normally prevents these, the actions must too) ---------------------------------------------

    public function test_the_review_action_refuses_unreviewable_states_and_invalid_decisions(): void
    {
        $doctor = $this->doctor()->user;
        $action = app(ReviewAssessment::class);

        $approved = MedicalAssessment::factory()->approved()->create(['user_id' => $this->client()->id]);
        $this->assertRuleViolation(fn () => $action->handle($approved, $doctor, AssessmentStatus::NotSuitable, 'x'), 'هذا التقييم ليس بانتظار المراجعة.');

        $submitted = $this->submittedAssessment($this->client());
        $this->assertRuleViolation(fn () => $action->handle($submitted, $doctor, AssessmentStatus::Draft), 'قرار غير صالح.');
        $this->assertRuleViolation(fn () => $action->handle($submitted, $doctor, AssessmentStatus::NeedsInfo, ' '), 'اكتب ملاحظة توضح القرار للعميل.');
    }

    public function test_the_upload_action_refuses_reviewed_tests_and_empty_uploads(): void
    {
        $client = $this->client();
        $action = app(UploadLabResult::class);

        $reviewed = LabRequest::factory()->create(['user_id' => $client->id, 'status' => LabStatus::Reviewed]);
        $this->assertRuleViolation(fn () => $action->handle($client, $reviewed, []), 'تمت مراجعة هذا التحليل بالفعل.');

        $open = LabRequest::factory()->create(['user_id' => $client->id]);
        $this->assertRuleViolation(fn () => $action->handle($client, $open, []), 'أرفق ملف النتيجة.');
    }

    public function test_the_log_dose_action_refuses_someone_elses_or_unscheduled_prescriptions(): void
    {
        $client = $this->client();
        $action = app(LogDose::class);

        $foreign = Prescription::factory()->due()->create();
        $this->assertRuleViolation(fn () => $action->handle($client, $foreign, DoseStatus::Taken), 'لا توجد جرعة مجدولة لتسجيلها.');

        $unscheduled = Prescription::factory()->create(['user_id' => $client->id, 'next_dose_at' => null]);
        $this->assertRuleViolation(fn () => $action->handle($client, $unscheduled, DoseStatus::Taken), 'لا توجد جرعة مجدولة لتسجيلها.');
    }

    public function test_the_business_rule_exception_renders_json_for_api_style_requests_and_a_redirect_otherwise(): void
    {
        Route::middleware('web')->get('/violation', fn () => throw new BusinessRuleViolation('رسالة للمستخدم'));

        $this->getJson('/violation')->assertStatus(422)->assertJson(['message' => 'رسالة للمستخدم']);
        $this->from('/somewhere')->get('/violation')->assertRedirect('/somewhere')->assertSessionHas('error', 'رسالة للمستخدم');
    }

    public function test_business_rule_violations_are_not_reported_as_errors(): void
    {
        $handler = app(ExceptionHandler::class);

        $this->assertFalse($handler->shouldReport(new BusinessRuleViolation('x')));
        $this->assertTrue($handler->shouldReport(new \RuntimeException('x')));
    }

    // ---- enums ------------------------------------------------------------------------------

    public function test_every_enum_case_has_a_label_and_status_enums_have_a_known_tone(): void
    {
        $tones = ['success', 'warning', 'danger', 'info', 'neutral'];

        foreach (glob(app_path('Enums/*.php')) as $file) {
            $class = 'App\\Enums\\'.basename($file, '.php');
            if (! enum_exists($class)) {
                continue;
            }

            foreach ($class::cases() as $case) {
                $this->assertNotSame('', $case->label(), "{$class}::{$case->name} needs a label");
                if (method_exists($case, 'tone')) {
                    $this->assertContains($case->tone(), $tones, "{$class}::{$case->name} tone");
                }
            }
            $this->assertSame(array_column($class::cases(), 'value'), $class::values());
            $this->assertSame(count($class::cases()), count($class::options()));
            $this->assertSame(array_keys($class::labels()), $class::values(), "{$class} labels must list every case in order");
        }
    }

    public function test_enum_helpers(): void
    {
        $this->assertTrue(OrderStatus::Delivered->isFinal());
        $this->assertTrue(OrderStatus::Rejected->isFinal());
        $this->assertFalse(OrderStatus::Preparing->isFinal());

        $this->assertTrue(PaymentMethod::Tabby->isInstallment());
        $this->assertFalse(PaymentMethod::Mada->isInstallment());
        $this->assertEquals([PaymentMethod::ApplePay, PaymentMethod::Mada], PaymentMethod::enabled());

        $this->assertTrue(AssessmentStatus::NeedsInfo->isEditableByClient());
        $this->assertFalse(AssessmentStatus::Submitted->isEditableByClient());

        $this->assertTrue(Role::Client->homeRoute() === 'account.dashboard');
        foreach (Role::cases() as $role) {
            $this->assertTrue(Route::has($role->homeRoute()), "{$role->value} home route exists");
        }
        $this->assertSame([Role::Doctor, Role::Dietitian, Role::CareTeam], array_values(array_filter(Role::cases(), fn (Role $r) => $r->canAccessMedicalData())));
        $this->assertSame(['success', 'warning', 'neutral'], [SubscriptionStatus::Active->tone(), SubscriptionStatus::Pending->tone(), SubscriptionStatus::Expired->tone()]);
        $this->assertSame('success', PaymentStatus::Paid->tone());
        $this->assertSame('success', ContactRequestStatus::Resolved->tone());
    }

    // ---- policies ------------------------------------------------------------------------------

    public function test_policies_for_subscriptions_payments_and_support_objects(): void
    {
        $owner = $this->client();
        $subscription = $this->subscribe($owner);
        $payment = $this->paidPayment($subscription);

        $this->assertTrue($owner->can('view', $subscription));
        $this->assertTrue($owner->can('manage', $subscription));
        $this->assertTrue($this->admin()->can('view', $subscription));
        $this->assertFalse($this->admin()->can('manage', $subscription));
        $this->assertFalse($this->client()->can('view', $payment));
        $this->assertTrue($this->staff(Role::Support)->can('view', $payment));
        $this->assertFalse($this->staff(Role::Support)->can('refund', $payment));
        $this->assertTrue($this->admin()->can('refund', $payment));
    }

    public function test_policies_for_clinical_objects(): void
    {
        $owner = $this->client();
        $report = SideEffectReport::factory()->create(['user_id' => $owner->id]);
        $lab = LabRequest::factory()->uploaded()->create(['user_id' => $owner->id]);
        $request = ContactRequest::factory()->create(['user_id' => $owner->id, 'target' => 'care_team']);
        $doctor = $this->doctor()->user;
        $care = $this->staff(Role::CareTeam);

        $this->assertTrue($owner->can('view', $report));
        $this->assertTrue($doctor->can('handle', $report));
        $this->assertTrue($care->can('handle', $report));
        $this->assertFalse($owner->can('handle', $report));
        $this->assertFalse($this->admin()->can('view', $report));

        $this->assertTrue($owner->can('upload', $lab));
        $this->assertTrue($doctor->can('review', $lab));
        $this->assertFalse($care->can('view', $lab));
        $this->assertFalse($owner->can('review', $lab));

        $this->assertTrue($care->can('handle', $request));
        $this->assertFalse($doctor->can('view', $request));
        $this->assertTrue($owner->can('view', $request));
    }

    public function test_policy_for_appointments_across_roles(): void
    {
        $doctor = $this->doctor();
        $owner = $this->client();
        $appointment = Appointment::factory()->create(['user_id' => $owner->id, 'practitioner_id' => $doctor->id]);
        $instant = Appointment::factory()->instantRequest()->create();

        $this->assertTrue($owner->can('view', $appointment));
        $this->assertTrue($doctor->user->can('view', $appointment));
        $this->assertTrue($this->staff(Role::CareTeam)->can('view', $appointment));
        $this->assertTrue($this->staff(Role::Support)->can('cancel', $appointment));
        $this->assertFalse($this->client()->can('view', $appointment));
        $this->assertFalse($this->doctor()->user->can('view', $appointment));
        $this->assertTrue($this->doctor()->user->can('view', $instant), 'any doctor can see the instant queue');
        $this->assertTrue($this->doctor()->user->can('claim', $instant));
        $this->assertFalse($this->client()->can('claim', $instant));
    }

    public function test_user_policy(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->assertTrue($admin->can('manage', $client));
        $this->assertFalse($admin->can('manage', $admin));
        $this->assertFalse($this->staff(Role::Support)->can('manage', $client));
        $this->assertTrue($this->doctor()->user->can('viewClinical', $client));
        $this->assertFalse($admin->can('viewClinical', $client));
        $this->assertTrue($client->can('viewClinical', $client));
        $this->assertFalse($this->doctor()->user->can('viewClinical', $this->staff(Role::Admin)));
    }

    // ---- models ------------------------------------------------------------------------------

    public function test_model_relations_resolve(): void
    {
        $doctor = $this->doctor();
        $client = $this->client();
        $subscription = $this->subscribe($client);
        $payment = $this->paidPayment($subscription);
        $rx = Prescription::factory()->create(['user_id' => $client->id, 'practitioner_id' => $doctor->id, 'product_id' => Product::factory()->create()->id]);
        $order = Order::factory()->create(['user_id' => $client->id, 'prescription_id' => $rx->id, 'pharmacy_id' => Pharmacy::factory()->create()->id]);
        $item = $order->items()->create(['name' => 'منتج', 'quantity' => 1, 'unit_price_halalas' => 100, 'is_available' => true, 'product_id' => $rx->product_id]);
        $event = $order->recordEvent(OrderStatus::Requested, 'ملاحظة', $client);
        $assessment = MedicalAssessment::factory()->approved()->create(['user_id' => $client->id, 'reviewed_by' => $doctor->user_id]);
        $appointment = Appointment::factory()->create(['user_id' => $client->id, 'practitioner_id' => $doctor->id, 'subscription_id' => $subscription->id]);
        $dose = DoseLog::create(['prescription_id' => $rx->id, 'user_id' => $client->id, 'scheduled_for' => now(), 'status' => 'taken', 'logged_at' => now()]);
        $report = SideEffectReport::factory()->create(['user_id' => $client->id, 'prescription_id' => $rx->id, 'handled_by' => $doctor->user_id]);
        $ticket = Ticket::factory()->create(['user_id' => $client->id, 'assigned_to' => $doctor->user_id]);
        $reply = $ticket->replies()->create(['user_id' => $client->id, 'body' => 'رد']);
        $plan = WeightPlan::factory()->create(['user_id' => $client->id, 'practitioner_id' => $doctor->id, 'assessment_id' => $assessment->id, 'dietitian_id' => $this->dietitian()->id]);
        $contact = ContactRequest::factory()->create(['user_id' => $client->id, 'assigned_to' => $doctor->user_id]);
        $audit = Audit::log('x', $client);
        $consent = app(RecordConsent::class)->handle($client, ConsentType::Marketing);

        $checks = [
            [$client->practitioner()->exists(), false],
            [$doctor->user->practitioner->is($doctor), true],
            [$client->subscriptions->first()->is($subscription), true],
            [$client->appointments->first()->is($appointment), true],
            [$client->assessments->first()->is($assessment), true],
            [$client->weightPlans->first()->is($plan), true],
            [$client->prescriptions->first()->is($rx), true],
            [$client->orders->first()->is($order), true],
            [$client->tickets->first()->is($ticket), true],
            [$client->consents->first()->is($consent), true],
            [$client->measurements()->count(), 0],
            [$payment->subscription->is($subscription), true],
            [$subscription->payments->first()->is($payment), true],
            [$subscription->appointments->first()->is($appointment), true],
            [$subscription->package instanceof Package, true],
            [$appointment->subscription->is($subscription), true],
            [$rx->doseLogs->first()->is($dose), true],
            [$rx->orders->first()->is($order), true],
            [$rx->appointment()->exists(), false],
            [$rx->product->is($order->items->first()->product), true],
            [$dose->prescription->is($rx), true],
            [$dose->user->is($client), true],
            [$item->order->is($order), true],
            [$item->product->is($rx->product), true],
            [$event->order->is($order), true],
            [$event->actor->is($client), true],
            [$order->prescription->is($rx), true],
            [$order->pharmacy->orders->first()->is($order), true],
            [$order->pharmacy->staff()->count(), 0],
            [$assessment->reviewer->is($doctor->user), true],
            [$report->prescription->is($rx), true],
            [$report->handler->is($doctor->user), true],
            [$report->attachments()->count(), 0],
            [$reply->author->is($client), true],
            [$reply->ticket->is($ticket), true],
            [$ticket->assignee->is($doctor->user), true],
            [$plan->assessment->is($assessment), true],
            [$plan->dietitian instanceof Practitioner, true],
            [$plan->user->is($client), true],
            [$contact->assignee->is($doctor->user), true],
            [$audit->user_id, null],
            [$audit->auditable->is($client), true],
            [$consent->user->is($client), true],
            [$doctor->appointments->first()->is($appointment), true],
            [$doctor->availabilities()->with('practitioner')->first()->practitioner->is($doctor), true],
            [$doctor->specialty(), 'استشاري غدد صماء وسمنة'],
            [$doctor->bio(), 'خبرة في إدارة الوزن والسمنة.'],
        ];

        foreach ($checks as $i => [$actual, $expected]) {
            $this->assertSame($expected, $actual, "relation check #{$i}");
        }
    }

    public function test_localised_attributes_follow_the_language_with_an_arabic_fallback(): void
    {
        $doctor = $this->doctor(['specialty_en' => 'Endocrinology', 'name_en' => 'Dr. Example', 'bio_en' => null], ['name' => 'د. مثال']);
        $package = Package::factory()->create(['name_en' => 'Six months', 'description_en' => '', 'features_en' => []]);
        $package->update(['features_ar' => ['ميزة']]);

        $this->assertSame('د. مثال', $doctor->displayName());
        $this->assertSame('استشاري غدد صماء وسمنة', $doctor->specialty());

        app()->setLocale('en');
        $this->assertSame('Dr. Example', $doctor->displayName());
        $this->assertSame('Endocrinology', $doctor->specialty());
        $this->assertSame('خبرة في إدارة الوزن والسمنة.', $doctor->bio(), 'falls back to Arabic when English is empty');
        $this->assertSame('Six months', $package->name());
        $this->assertSame('برنامج إدارة الوزن بإشراف طبي لمدة 6 أشهر.', $package->description());
        $this->assertSame(['ميزة'], $package->features());
    }

    public function test_subscription_helpers(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 12:00:00'));
        $subscription = $this->subscribe($this->client(), ['ends_at' => now()->addDays(10)->addHours(3), 'followups_included' => 3, 'followups_used' => 1]);

        $this->assertSame(11, $subscription->daysLeft());
        $this->assertSame(2, $subscription->remainingFollowups());

        $this->travel(30)->days();
        $this->assertSame(0, $subscription->daysLeft());
        $this->assertFalse($subscription->fresh()->isCurrent());
    }

    // ---- middleware & plumbing ------------------------------------------------------------------------------

    public function test_a_corrupt_locale_in_the_session_falls_back_to_arabic(): void
    {
        $this->withSession(['locale' => 'xx'])->get(route('home'))->assertSee('lang="ar"', false);
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $this->get(route('home'))->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    public function test_the_rate_limiters_are_registered(): void
    {
        foreach (['login', 'register', 'password-reset', 'sensitive', 'uploads', 'webhooks'] as $name) {
            $this->assertNotNull(RateLimiter::limiter($name), "limiter [{$name}] is registered");
        }
    }

    public function test_notification_mail_is_generic_and_links_back_to_the_account(): void
    {
        $client = $this->client(['name' => 'منى']);
        $notification = new GenericNotification('تمت إضافة وصفة جديدة إلى حسابك', 'اطّلع على التفاصيل من حسابك.', 'https://itizan.test/account');

        $mail = $notification->toMail($client);

        $this->assertSame('تمت إضافة وصفة جديدة إلى حسابك', $mail->subject);
        $this->assertSame('أهلًا منى', $mail->greeting);
        $this->assertSame('https://itizan.test/account', $mail->actionUrl);
        $this->assertContains('هذه رسالة عامة ولا تتضمن معلومات صحية. التفاصيل متاحة داخل حسابك.', $mail->outroLines);
    }

    public function test_notifications_render_in_the_recipients_language(): void
    {
        $client = $this->client(['locale' => 'en']);
        $client->notify(new WelcomeNotification);

        $this->assertSame('Welcome to Itizan', $client->notifications()->first()->data['title']);
    }

    public function test_the_package_comparison_ignores_packages_that_do_not_divide_evenly(): void
    {
        $five = Package::factory()->create(['duration_months' => 5, 'price_halalas' => 50000]);
        $twelve = Package::factory()->annual()->create();

        $this->assertNull((new PackageComparison)->longestPackageSavings(collect([$five, $twelve])));
    }

    private function assertRuleViolation(callable $callback, string $message): void
    {
        try {
            $callback();
        } catch (BusinessRuleViolation $e) {
            $this->assertSame($message, $e->getMessage());

            return;
        }

        $this->fail("Expected a business rule violation with message [{$message}]");
    }
}
