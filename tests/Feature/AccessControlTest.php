<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\LabRequest;
use App\Models\Measurement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Subscription;
use App\Models\Ticket;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The authorisation safety net. It walks the real route table, so a route added later without the
 * right middleware is caught automatically.
 */
class AccessControlTest extends TestCase
{
    /** @return list<LaravelRoute> */
    private function roleProtectedRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route) => collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'role:')))
            ->values()
            ->all();
    }

    /** @return list<Role> */
    private function allowedRoles(LaravelRoute $route): array
    {
        $middleware = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'role:'));

        return array_map(fn (string $role) => Role::from($role), explode(',', substr($middleware, 5)));
    }

    public function test_the_route_table_contains_protected_routes(): void
    {
        $this->assertGreaterThan(80, count($this->roleProtectedRoutes()));
    }

    public function test_guests_are_redirected_to_login_on_every_role_protected_route(): void
    {
        foreach ($this->roleProtectedRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $uri = preg_replace('/\{[^}]+\}/', '1', '/'.ltrim($route->uri(), '/'));

                $response = $this->call($method, $uri);

                $this->assertSame(302, $response->getStatusCode(), "{$method} {$uri} should redirect guests");
                $this->assertStringEndsWith('/login', $response->headers->get('Location'), "{$method} {$uri} should send guests to login");
            }
        }
    }

    public function test_users_with_the_wrong_role_get_403_on_every_role_protected_route(): void
    {
        $users = collect(Role::cases())->mapWithKeys(fn (Role $role) => [
            $role->value => $role === Role::Pharmacy ? $this->pharmacist() : $this->staff($role),
        ]);

        foreach ($this->roleProtectedRoutes() as $route) {
            $allowed = $this->allowedRoles($route);

            foreach (Role::cases() as $role) {
                if (in_array($role, $allowed, true)) {
                    continue;
                }

                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    $uri = preg_replace('/\{[^}]+\}/', '1', '/'.ltrim($route->uri(), '/'));

                    $this->actingAs($users[$role->value])->call($method, $uri)
                        ->assertForbidden("{$role->value} must not reach {$method} {$uri}");
                }
            }
        }
    }

    public function test_the_role_check_runs_before_model_binding_so_ids_cannot_be_probed(): void
    {
        $client = $this->client();

        // Record 999999 does not exist, yet a client still gets 403 (not 404) on staff routes.
        $this->actingAs($client)->get('/admin/clients/999999')->assertForbidden();
        $this->actingAs($client)->get('/practitioner/assessments/999999')->assertForbidden();
        $this->actingAs($client)->get('/pharmacy/orders/999999')->assertForbidden();
    }

    public function test_payment_webhook_is_the_only_public_post_outside_authentication(): void
    {
        $publicPosts = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (LaravelRoute $route) => in_array('POST', $route->methods(), true))
            ->reject(fn (LaravelRoute $route) => collect($route->gatherMiddleware())->contains(fn ($m) => in_array($m, ['auth', 'guest'], true)))
            ->map(fn (LaravelRoute $route) => $route->uri())
            ->values()
            ->all();

        $this->assertSame(['webhooks/payments'], $publicPosts);
    }

    public function test_every_authenticated_route_uses_the_auth_middleware(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'account') || str_starts_with($route->uri(), 'admin')
                || str_starts_with($route->uri(), 'practitioner') || str_starts_with($route->uri(), 'care')
                || str_starts_with($route->uri(), 'pharmacy') || str_starts_with($route->uri(), 'delivery')) {
                $this->assertContains('auth', $route->gatherMiddleware(), "{$route->uri()} must require authentication");
            }
        }
    }

    public function test_clients_cannot_reach_another_clients_records(): void
    {
        $owner = $this->client();
        $intruder = $this->client();

        $appointment = Appointment::factory()->for($owner, 'user')->create();
        $prescription = Prescription::factory()->for($owner, 'user')->create();
        $order = Order::factory()->for($owner, 'user')->create();
        $ticket = Ticket::factory()->for($owner, 'user')->create();
        $lab = LabRequest::factory()->for($owner, 'user')->create();
        $measurement = Measurement::factory()->for($owner, 'user')->create();
        $subscription = $this->subscribe($owner);
        $payment = $this->paidPayment($subscription);

        $this->actingAs($intruder);

        $this->get(route('account.appointments.show', $appointment))->assertForbidden();
        $this->post(route('account.appointments.cancel', $appointment))->assertForbidden();
        $this->get(route('account.medications.show', $prescription))->assertForbidden();
        $this->post(route('account.medications.doses.store', $prescription), ['status' => 'missed'])->assertForbidden();
        $this->get(route('account.orders.show', $order))->assertForbidden();
        $this->post(route('account.orders.cancel', $order))->assertForbidden();
        $this->get(route('account.tickets.show', $ticket))->assertForbidden();
        $this->post(route('account.tickets.reply', $ticket), ['body' => 'hello there'])->assertForbidden();
        $this->post(route('account.labs.upload', $lab))->assertForbidden();
        $this->delete(route('account.progress.destroy', $measurement))->assertForbidden();
        $this->get(route('account.invoices.show', $payment))->assertForbidden();
        $this->get(route('checkout.sandbox.show', $this->pendingPaymentFor($owner)))->assertForbidden();
    }

    private function pendingPaymentFor($user)
    {
        $subscription = Subscription::factory()->pending()->create(['user_id' => $user->id]);

        return Payment::factory()->create(['user_id' => $user->id, 'subscription_id' => $subscription->id]);
    }
}
