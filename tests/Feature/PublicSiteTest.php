<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Practitioner;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    public function test_every_public_page_renders(): void
    {
        foreach (['home', 'how-it-works', 'services', 'team', 'pricing', 'faq', 'prescription-journey', 'about', 'contact', 'policies.index', 'login', 'register', 'password.request'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_home_page_carries_the_positioning_copy_and_free_consultation_call_to_action(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('رحلتك لوزن صحي… تبدأ باتزان')
            ->assertSee('رعاية طبية ومتابعة مستمرة تساعدك تبدأ بخطة تناسبك، وتكمل بثقة.')
            ->assertSee('اتزان — رحلة إدارة الوزن بإشراف طبي، خطوة بخطوة.')
            ->assertSee('ابدأ باستشارة مجانية')
            ->assertSee('تعرّف على البرنامج')
            ->assertSee('الاستشارة المجانية تعريفية بالبرنامج، وليست تشخيصًا ولا وصفًا للعلاج.')
            ->assertSee('استشارة مجانية واحدة للعملاء الجدد. تعرّف فيها على البرنامج والخطوة المناسبة لك قبل اختيار اشتراكك.');
    }

    public function test_free_consultation_offer_appears_before_the_packages_on_home_and_pricing(): void
    {
        $home = $this->get(route('home'))->getContent();
        $this->assertLessThan(mb_strpos($home, 'id="packages-title"'), mb_strpos($home, route('free-consultation')), 'the hero call to action comes before the packages on the home page');

        foreach (['pricing'] as $route) {
            $html = $this->get(route($route))->getContent();

            $this->assertLessThan(
                mb_strpos($html, 'id="packages-title"') ?: mb_strpos($html, 'pkg-'),
                mb_strpos($html, 'id="free-consult-title-'),
                "The free consultation must come before the packages on [{$route}]",
            );
        }
    }

    public function test_the_free_consultation_block_can_repeat_without_duplicate_ids(): void
    {
        $html = Blade::render('<x-free-consultation /><x-free-consultation />');

        preg_match_all('/id="(free-consult-title-[a-z0-9]+)"/', $html, $matches);

        $this->assertGreaterThanOrEqual(2, count($matches[1]), 'the block can be rendered more than once on a page');
        $this->assertSame($matches[1], array_unique($matches[1]), 'every instance needs its own id');
    }

    public function test_pricing_shows_both_packages_with_the_exact_brief_numbers(): void
    {
        $this->get(route('pricing'))
            ->assertOk()
            ->assertSee('999')
            ->assertSee('1,499')
            ->assertSee('166.50')
            ->assertSee('124.92')
            ->assertSee('499 ريال')
            ->assertSee('يقارب 25%')
            ->assertSee('249.75')
            ->assertSee('374.75')
            ->assertSee('الدواء والتحاليل غير مشمولين ما لم يُذكر خلاف ذلك.');
    }

    public function test_buy_now_pay_later_is_described_as_pending_until_the_merchant_account_is_approved(): void
    {
        $this->get(route('pricing'))->assertSee('تُتاح بعد اعتماد حساب التاجر وقبول نوع الخدمة.');

        config(['itizan.payments.methods.tabby' => true]);

        $this->get(route('pricing'))->assertDontSee('تُتاح بعد اعتماد حساب التاجر وقبول نوع الخدمة.');
    }

    public function test_only_verified_practitioners_are_listed_publicly(): void
    {
        $verified = $this->doctor([], ['name' => 'د. موثّق ظاهر']);
        $unverified = Practitioner::factory()->unverified()->create();
        $unverified->user->update(['name' => 'د. غير موثّق مخفي']);

        $expired = Practitioner::factory()->create(['license_expires_on' => now()->subDay()]);
        $expired->user->update(['name' => 'د. منتهي الترخيص']);

        $this->get(route('team'))
            ->assertOk()
            ->assertSee('د. موثّق ظاهر')
            ->assertSee($verified->license_number)
            ->assertDontSee('د. غير موثّق مخفي')
            ->assertDontSee('د. منتهي الترخيص');

        $this->get(route('home'))->assertSee('د. موثّق ظاهر')->assertDontSee('د. غير موثّق مخفي');
    }

    public function test_whatsapp_button_is_on_public_pages_with_the_prefilled_support_message(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('https://wa.me/966551475212?text=', false);
        $response->assertSee(rawurlencode('السلام عليكم، أنا عميل برنامج اتزان، وأحتاج مساعدة بخصوص: '), false);
    }

    public function test_emergency_guidance_is_in_the_footer_of_every_public_page(): void
    {
        $this->get(route('home'))
            ->assertSee('اتزان ليست خدمة طوارئ.')
            ->assertSee('997');
    }

    public function test_policy_pages_render_markdown_and_strip_raw_html(): void
    {
        Page::factory()->create([
            'slug' => 'xss-check',
            'title_ar' => 'اختبار',
            'body_ar' => "## عنوان آمن\n\n<script>alert('x')</script>\n\n[رابط](javascript:alert(1))",
        ]);

        $response = $this->get(route('policies.show', 'xss-check'))->assertOk();

        $response->assertSee('عنوان آمن');
        $response->assertDontSee('<script>alert', false);
        $response->assertDontSee('javascript:alert', false);
    }

    public function test_unpublished_policy_page_is_not_found(): void
    {
        Page::factory()->create(['slug' => 'hidden', 'is_published' => false]);

        $this->get(route('policies.show', 'hidden'))->assertNotFound();
    }

    public function test_seeded_policies_cover_the_required_topics(): void
    {
        foreach (['privacy', 'terms', 'subscription-renewal-cancellation-refund', 'delivery', 'patient-rights', 'complaints', 'medical-disclaimer'] as $slug) {
            $this->get(route('policies.show', $slug))->assertOk();
        }
    }

    public function test_faq_lists_published_questions_only(): void
    {
        Faq::factory()->create(['question_ar' => 'سؤال ظاهر للجميع', 'sort_order' => 99]);
        Faq::factory()->create(['question_ar' => 'سؤال مخفي', 'is_published' => false]);

        $this->get(route('faq'))->assertSee('سؤال ظاهر للجميع')->assertDontSee('سؤال مخفي');
    }

    public function test_pages_have_accessibility_landmarks_and_document_language(): void
    {
        $this->get(route('home'))
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('id="main"', false)
            ->assertSee('class="skip-link"', false)
            ->assertSee('<main', false);
    }

    public function test_free_consultation_entry_sends_guests_to_registration(): void
    {
        $this->get(route('free-consultation'))->assertRedirect(route('register'));

        $this->assertSame(
            route('account.appointments.create', ['type' => 'free_intro']),
            session('url.intended'),
        );
    }

    public function test_signed_in_client_goes_straight_to_booking_from_the_call_to_action(): void
    {
        $this->actingAs($this->client())
            ->get(route('free-consultation'))
            ->assertRedirect(route('account.appointments.create', ['type' => 'free_intro']));
    }
}
