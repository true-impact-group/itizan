<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Faq;
use App\Models\Package;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SandboxGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class ContentAndCatalogTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->admin = $this->admin();
    }

    // ---- packages ------------------------------------------------------------------------------

    private function packagePayload(array $overrides = []): array
    {
        return [
            'name_ar' => 'الباقة النصف سنوية', 'name_en' => 'Six-month package', 'price' => '1099.50', 'included_followups' => '4',
            'vat_inclusive' => '1', 'is_active' => '1', 'sort_order' => '1',
            'features_ar' => "ميزة أولى\n\nميزة ثانية", 'features_en' => "First\nSecond",
            ...$overrides,
        ];
    }

    public function test_an_admin_changes_a_package_price_stored_exactly_in_halalas(): void
    {
        $package = Package::where('slug', 'six-months')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.packages.update', $package), $this->packagePayload())->assertRedirect(route('admin.packages.index'));

        $package->refresh();
        $this->assertSame(109950, $package->price_halalas);
        $this->assertSame(4, $package->included_followups);
        $this->assertSame(['ميزة أولى', 'ميزة ثانية'], $package->features_ar);
        $this->assertDatabaseHas('audit_logs', ['action' => 'package.updated']);
    }

    public function test_a_price_change_does_not_touch_existing_subscriptions(): void
    {
        $package = Package::where('slug', 'six-months')->firstOrFail();
        $subscription = $this->subscribe($this->client(), [], $package);

        $this->actingAs($this->admin)->put(route('admin.packages.update', $package), $this->packagePayload(['price' => '2000']));

        $this->assertSame(99900, $subscription->fresh()->price_halalas);
    }

    public function test_the_public_pricing_page_follows_the_new_price(): void
    {
        $package = Package::where('slug', 'six-months')->firstOrFail();
        $this->actingAs($this->admin)->put(route('admin.packages.update', $package), $this->packagePayload(['price' => '1200']));

        $this->get(route('pricing'))->assertSee('1,200')->assertSee('200 ريال');
    }

    public function test_package_input_is_validated(): void
    {
        $package = Package::where('slug', 'six-months')->firstOrFail();
        $put = fn (array $o) => $this->actingAs($this->admin)->put(route('admin.packages.update', $package), $this->packagePayload($o));

        $put(['price' => '0'])->assertSessionHasErrors('price');
        $put(['price' => 'free'])->assertSessionHasErrors('price');
        $put(['included_followups' => '-1'])->assertSessionHasErrors('included_followups');
        $put(['included_followups' => '500'])->assertSessionHasErrors('included_followups');
        $put(['name_ar' => ''])->assertSessionHasErrors('name_ar');

        $this->assertSame(99900, $package->fresh()->price_halalas);
    }

    public function test_deactivating_a_package_removes_it_from_the_pricing_page(): void
    {
        $package = Package::where('slug', 'annual')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.packages.update', $package), $this->packagePayload(['is_active' => '0', 'name_ar' => 'الباقة السنوية', 'name_en' => 'Annual package']));

        $this->get(route('pricing'))->assertDontSee('الباقة السنوية');
    }

    public function test_the_database_refuses_a_non_positive_package_price(): void
    {
        $this->expectException(QueryException::class);

        Package::factory()->create(['price_halalas' => 0]);
    }

    public function test_the_database_refuses_a_subscription_that_ends_before_it_starts(): void
    {
        $this->expectException(QueryException::class);

        Subscription::factory()->create(['starts_at' => now(), 'ends_at' => now()->subDay()]);
    }

    public function test_the_database_refuses_more_follow_ups_used_than_included(): void
    {
        $this->expectException(QueryException::class);

        Subscription::factory()->create(['followups_included' => 2, 'followups_used' => 3]);
    }

    // ---- refunds ------------------------------------------------------------------------------

    public function test_an_admin_refunds_a_paid_payment_and_the_subscription_is_cancelled(): void
    {
        $subscription = $this->subscribe($this->client(), ['auto_renew' => true]);
        $payment = $this->paidPayment($subscription);

        $this->actingAs($this->admin)->post(route('admin.payments.refund', $payment))->assertSessionHas('status', 'تم استرداد المبلغ وإلغاء الاشتراك.');

        $payment->refresh();
        $subscription->refresh();
        $this->assertSame(PaymentStatus::Refunded, $payment->status);
        $this->assertSame($payment->amount_halalas, $payment->refunded_halalas);
        $this->assertNotNull($payment->refunded_at);
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertSame('refunded', $subscription->cancel_reason);
        $this->assertFalse($subscription->auto_renew);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.refunded', 'user_id' => $this->admin->id]);
    }

    public function test_only_paid_payments_can_be_refunded_and_only_once(): void
    {
        $pending = Payment::factory()->create();
        $paid = $this->paidPayment($this->subscribe($this->client()));

        $this->actingAs($this->admin)->post(route('admin.payments.refund', $pending))->assertSessionHas('error', 'يمكن استرداد المدفوعات المكتملة فقط.');

        $this->actingAs($this->admin)->post(route('admin.payments.refund', $paid));
        $this->actingAs($this->admin)->post(route('admin.payments.refund', $paid))->assertSessionHas('error');
    }

    public function test_a_provider_that_rejects_the_refund_leaves_everything_unchanged(): void
    {
        $this->app->bind(PaymentGateway::class, fn () => new class extends SandboxGateway
        {
            public function refund(Payment $payment): bool
            {
                return false;
            }
        });
        $payment = $this->paidPayment($this->subscribe($this->client()));

        $this->actingAs($this->admin)->post(route('admin.payments.refund', $payment))->assertSessionHas('error', 'رفض مزود الدفع عملية الاسترداد.');

        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $payment->subscription->fresh()->status);
    }

    public function test_the_payments_list_filters_by_status(): void
    {
        $this->paidPayment($this->subscribe($this->client(['name' => 'عميل دفع ناجح'])));
        Payment::factory()->create(['user_id' => $this->client(['name' => 'عميل دفع معلق'])->id, 'subscription_id' => Subscription::factory()->pending()->create()->id]);

        $this->actingAs($this->admin)->get(route('admin.payments.index', ['status' => 'paid']))->assertSee('عميل دفع ناجح')->assertDontSee('عميل دفع معلق');
    }

    // ---- CMS ------------------------------------------------------------------------------

    public function test_an_admin_edits_a_policy_page_in_both_languages(): void
    {
        $page = Page::where('slug', 'delivery')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.pages.update', $page), [
            'title_ar' => 'سياسة التوصيل المحدثة', 'title_en' => 'Updated delivery policy',
            'body_ar' => '## محدّث', 'body_en' => '## Updated', 'is_published' => '1',
        ])->assertRedirect(route('admin.pages.index'));

        $page->refresh();
        $this->assertSame($this->admin->id, $page->updated_by);
        $this->get(route('policies.show', 'delivery'))->assertSee('سياسة التوصيل المحدثة');
        $this->get(route('locale', 'en'));
        $this->get(route('policies.show', 'delivery'))->assertSee('Updated delivery policy');
    }

    public function test_the_page_editor_requires_both_languages(): void
    {
        $page = Page::where('slug', 'delivery')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.pages.update', $page), ['title_ar' => 'x', 'title_en' => '', 'body_ar' => 'x', 'body_en' => ''])
            ->assertSessionHasErrors(['title_en', 'body_en']);
    }

    public function test_unticking_published_hides_the_page(): void
    {
        $page = Page::where('slug', 'delivery')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.pages.update', $page), ['title_ar' => 'x', 'title_en' => 'x', 'body_ar' => 'x', 'body_en' => 'x']);

        $this->get(route('policies.show', 'delivery'))->assertNotFound();
    }

    public function test_the_faq_crud_and_ordering(): void
    {
        Faq::query()->delete();

        $this->actingAs($this->admin)->post(route('admin.faqs.store'), [
            'question_ar' => 'سؤال ثان', 'answer_ar' => 'جواب', 'question_en' => 'Second', 'answer_en' => 'Answer', 'sort_order' => 2, 'is_published' => '1',
        ])->assertRedirect(route('admin.faqs.index'));
        $this->actingAs($this->admin)->post(route('admin.faqs.store'), [
            'question_ar' => 'سؤال أول', 'answer_ar' => 'جواب', 'question_en' => 'First', 'answer_en' => 'Answer', 'sort_order' => 1, 'is_published' => '1',
        ]);

        $html = $this->get(route('faq'))->getContent();
        $this->assertLessThan(mb_strpos($html, 'سؤال ثان'), mb_strpos($html, 'سؤال أول'));

        $faq = Faq::where('question_ar', 'سؤال ثان')->firstOrFail();
        $this->actingAs($this->admin)->put(route('admin.faqs.update', $faq), ['question_ar' => 'سؤال معدّل', 'answer_ar' => 'ج', 'question_en' => 'Edited', 'answer_en' => 'A', 'sort_order' => 3]);
        $this->assertSame('سؤال معدّل', $faq->fresh()->question_ar);
        $this->assertFalse($faq->fresh()->is_published);

        $this->actingAs($this->admin)->delete(route('admin.faqs.destroy', $faq))->assertRedirect(route('admin.faqs.index'));
        $this->assertSoftDeleted($faq);
    }

    public function test_faq_input_is_validated(): void
    {
        $this->actingAs($this->admin)->post(route('admin.faqs.store'), ['question_ar' => '', 'answer_ar' => '', 'question_en' => '', 'answer_en' => ''])
            ->assertSessionHasErrors(['question_ar', 'answer_ar', 'question_en', 'answer_en']);
    }

    // ---- catalogue ------------------------------------------------------------------------------

    public function test_products_are_managed_in_riyals_and_stored_in_halalas(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'sku' => 'NEW-1', 'name_ar' => 'منتج جديد', 'name_en' => 'New product', 'type' => 'supplement', 'price' => '49.90', 'in_stock' => '1', 'is_active' => '1',
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::where('sku', 'NEW-1')->firstOrFail();
        $this->assertSame(4990, $product->price_halalas);
        $this->assertFalse($product->requires_prescription);
    }

    public function test_medication_products_always_require_a_prescription(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), [
            'sku' => 'MED-1', 'name_ar' => 'دواء', 'name_en' => 'Medication', 'type' => 'medication', 'price' => '300', 'requires_prescription' => '0',
        ]);

        $this->assertTrue(Product::where('sku', 'MED-1')->firstOrFail()->requires_prescription);
    }

    public function test_product_sku_must_be_unique_and_price_non_negative(): void
    {
        $this->actingAs($this->admin)->post(route('admin.products.store'), ['sku' => 'SUP-VITD', 'name_ar' => 'س', 'name_en' => 's', 'type' => 'supplement', 'price' => '1'])->assertSessionHasErrors('sku');
        $this->actingAs($this->admin)->post(route('admin.products.store'), ['sku' => 'X', 'name_ar' => 'س', 'name_en' => 's', 'type' => 'supplement', 'price' => '-1'])->assertSessionHasErrors('price');
        $this->actingAs($this->admin)->post(route('admin.products.store'), ['sku' => 'X', 'name_ar' => 'س', 'name_en' => 's', 'type' => 'herb', 'price' => '1'])->assertSessionHasErrors('type');
    }

    public function test_a_product_can_be_edited_and_soft_deleted(): void
    {
        $product = Product::where('sku', 'SUP-VITD')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.products.update', $product), [
            'sku' => 'SUP-VITD', 'name_ar' => 'فيتامين د 5000', 'name_en' => 'Vitamin D 5000', 'type' => 'supplement', 'price' => '60', 'is_active' => '1',
        ]);
        $this->assertSame(6000, $product->fresh()->price_halalas);
        $this->assertFalse($product->fresh()->in_stock);

        $this->actingAs($this->admin)->delete(route('admin.products.destroy', $product));
        $this->assertSoftDeleted($product);
    }

    public function test_soft_deleted_records_are_hidden_but_recoverable(): void
    {
        $faq = Faq::first();
        $faq->delete();

        $this->assertNull(Faq::find($faq->id));
        $this->assertNotNull(Faq::withTrashed()->find($faq->id));
        $faq->restore();
        $this->assertNotNull(Faq::find($faq->id));
    }

    public function test_every_domain_model_uses_soft_deletes_except_the_append_only_ledgers(): void
    {
        $ledgers = ['AuditLog', 'ConsentRecord', 'OrderEvent'];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $name = basename($file, '.php');
            $class = "App\\Models\\{$name}";
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }
            if (in_array($name, [...$ledgers, 'PractitionerAvailability'], true)) {
                $this->assertNotContains(SoftDeletes::class, class_uses_recursive($class), "{$name} is an append-only ledger");

                continue;
            }

            $this->assertContains(SoftDeletes::class, class_uses_recursive($class), "{$name} must use soft deletes");
        }
    }

    // ---- reports ------------------------------------------------------------------------------

    public function test_the_report_counts_subscriptions_renewals_payments_refunds_and_more_for_the_period(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $package = Package::where('slug', 'six-months')->firstOrFail();

        $first = $this->client();
        $old = $this->subscribe($first, ['created_at' => now()->subDays(200), 'status' => 'expired', 'starts_at' => now()->subDays(200), 'ends_at' => now()->subDays(20)], $package);
        $renewal = $this->subscribe($first, ['created_at' => now()->subDays(2)], $package);
        $this->paidPayment($renewal, ['amount_halalas' => 99900, 'vat_halalas' => 13030, 'paid_at' => now()->subDays(2)]);

        $newcomer = $this->client();
        $fresh = $this->subscribe($newcomer, ['created_at' => now()->subDay()], Package::where('slug', 'annual')->firstOrFail());
        $this->paidPayment($fresh, ['amount_halalas' => 149900, 'vat_halalas' => 19552, 'paid_at' => now()->subDay(), 'method' => 'apple_pay']);

        $refunded = $this->subscribe($this->client(), ['created_at' => now()->subDays(3)], $package);
        Payment::factory()->create(['user_id' => $refunded->user_id, 'subscription_id' => $refunded->id, 'status' => PaymentStatus::Refunded, 'refunded_at' => now()->subDay(), 'refunded_halalas' => 99900]);
        $refunded->update(['status' => 'cancelled', 'cancelled_at' => now()->subDay(), 'cancel_reason' => 'refunded']);

        $report = $this->actingAs($this->admin)->get(route('admin.reports', ['from' => now()->subDays(10)->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()->viewData('report');

        $this->assertSame(2, $report['subscriptions']['new'], 'the renewal and the newcomer (the old one is outside the period, the refunded one is cancelled)');
        $this->assertSame(1, $report['subscriptions']['renewals']);
        $this->assertSame(1, $report['subscriptions']['cancelled']);
        $this->assertSame(2, $report['payments']['count']);
        $this->assertSame(99900 + 149900, $report['payments']['revenue_halalas']);
        $this->assertSame(13030 + 19552, $report['payments']['vat_halalas']);
        $this->assertSame(1, $report['payments']['refund_count']);
        $this->assertSame(99900, $report['payments']['refund_halalas']);
        $this->assertEquals(['mada' => 1, 'apple_pay' => 1], $report['payments']['byMethod']);
        $this->assertEquals(['الباقة النصف سنوية' => 1, 'الباقة السنوية' => 1], $report['subscriptions']['byPackage']);
    }

    public function test_the_report_period_is_validated_and_defaults_to_the_last_30_days(): void
    {
        $this->actingAs($this->admin)->get(route('admin.reports', ['from' => '2026-10-10', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
        $this->actingAs($this->admin)->get(route('admin.reports', ['from' => 'yesterday']))->assertSessionHasErrors('from');

        $report = $this->actingAs($this->admin)->get(route('admin.reports'))->assertOk()->viewData('report');
        $this->assertTrue($report['range']['from']->isSameDay(now()->subDays(29)));
    }

    public function test_the_abandoned_checkouts_do_not_count_as_cancellations(): void
    {
        $this->subscribe($this->client(), ['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'abandoned']);

        $report = $this->actingAs($this->admin)->get(route('admin.reports'))->viewData('report');

        $this->assertSame(0, $report['subscriptions']['cancelled']);
    }
}
