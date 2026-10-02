<?php

namespace App\Providers;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SandboxGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            $driver = config('itizan.payments.driver');

            return match ($driver) {
                'sandbox' => $this->app->isProduction()
                    ? throw new InvalidArgumentException('The sandbox payment driver cannot be used in production. Configure a real PAYMENT_DRIVER.')
                    : new SandboxGateway,
                default => throw new InvalidArgumentException("Unsupported payment driver [{$driver}]."),
            };
        });
    }

    public function boot(): void
    {
        // Fail loudly in development and tests: N+1 lazy loading, silently dropped or missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Paginator::defaultView('vendor.pagination.itizan');
        Paginator::defaultSimpleView('vendor.pagination.itizan');

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('login')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        // Medical and money actions per signed-in user.
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
