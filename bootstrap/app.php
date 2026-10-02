<?php

use App\Exceptions\BusinessRuleViolation;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        // Laravel's built-in liveness probe. A deeper readiness check lives at /health.
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind a load balancer, set TRUSTED_PROXIES (comma separated IPs, or "*").
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }

        $middleware->web(prepend: [AssignRequestId::class]);
        $middleware->web(append: [SetLocale::class, EnsureAccountIsActive::class, SecurityHeaders::class]);

        $middleware->alias(['role' => EnsureRole::class]);

        // Check the role BEFORE route-model binding, otherwise a wrong-role user could tell which record ids
        // exist (404 vs 403) just by probing URLs.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureRole::class);

        // Server-to-server payment callbacks authenticate with an HMAC signature instead of a CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route(auth()->user()->role->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Expected business-rule messages are shown to the user, not logged as errors.
        $exceptions->dontReport(BusinessRuleViolation::class);

        // Every reported exception carries the request id and the signed-in user id (never PII).
        $exceptions->context(fn () => [
            'request_id' => request()?->attributes->get('request_id'),
            'user_id' => auth()->id(),
            'url' => request()?->path(),
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'webhooks/*', 'health') || $request->expectsJson(),
        );
    })->create();
