<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HealthAndErrorsTest extends TestCase
{
    public function test_health_check_reports_ok_and_exposes_no_internals(): void
    {
        $response = $this->getJson(route('health'))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok')
            ->assertHeader('X-Request-Id');

        $this->assertSame(['status', 'checks', 'time'], array_keys($response->json()), 'no internals in the body');
    }

    public function test_health_check_fails_with_503_and_logs_when_a_dependency_is_down(): void
    {
        Log::spy();
        Cache::shouldReceive('put')->andThrow(new \RuntimeException('cache backend down'));

        $this->getJson(route('health'))
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.cache', 'fail')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonMissing(['error' => 'cache backend down']);

        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'health.check_failed' && $context['check'] === 'cache');
    }

    public function test_laravel_liveness_probe_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_every_response_carries_a_request_id_and_honours_an_incoming_one(): void
    {
        $this->get(route('home'))->assertHeader('X-Request-Id');

        $this->withHeaders(['X-Request-Id' => 'trace-12345678'])->get(route('home'))->assertHeader('X-Request-Id', 'trace-12345678');
    }

    public function test_an_unsafe_incoming_request_id_is_replaced(): void
    {
        $response = $this->withHeaders(['X-Request-Id' => "bad id\r\nX-Injected: 1"])->get(route('home'));

        $this->assertNotSame("bad id\r\nX-Injected: 1", $response->headers->get('X-Request-Id'));
    }

    public function test_security_headers_are_set(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy');
    }

    public function test_pages_for_signed_in_users_are_not_cacheable(): void
    {
        $response = $this->actingAs($this->client())->get(route('account.dashboard'));

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_friendly_error_pages_render_in_arabic(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('لم نجد هذه الصفحة');
    }

    public function test_forbidden_page_is_friendly(): void
    {
        $this->actingAs($this->client())->get(route('admin.dashboard'))->assertForbidden()->assertSee('لا تملك صلاحية الوصول');
    }

    public function test_server_error_page_shows_a_reference_number_and_hides_the_exception(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/boom', fn () => throw new \RuntimeException('secret detail'));

        $this->get('/boom')
            ->assertStatus(500)
            ->assertSee('حدث خطأ من جانبنا')
            ->assertSee('رقم المرجع للدعم')
            ->assertDontSee('secret detail');
    }

    public function test_error_pages_render_without_touching_auth_or_the_database(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            $this->assertStringContainsString((string) $status, view('errors.'.$status)->render());
        }
    }

    public function test_json_clients_get_json_errors(): void
    {
        $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message']);
    }
}
