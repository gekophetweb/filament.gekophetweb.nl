<?php

use App\Http\Middleware\EnsureIpAllowed;
use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\ThrottleAuthFailures;
use App\Http\Middleware\ThrottleBeforeAuth;
use App\Support\LocalEnvironment;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

// Settings this installation wrote itself (storage/app/.env.local) win over .env. Tests use phpunit.xml only.
if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
    LocalEnvironment::load(dirname(__DIR__));
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => Route::group([], base_path('routes/docs.php')),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Only the fixed Docker frontend subnet (cloudflared) is trusted; never "*".
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));
        $middleware->trustProxies(
            at: $proxies,
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT,
        );

        $middleware->alias([
            'ip' => EnsureIpAllowed::class,
            'auth-failures' => ThrottleAuthFailures::class,
            'throttle-ip' => ThrottleBeforeAuth::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // Laravel sorts route middleware by priority and would otherwise run auth
        // first. IP check, then pre-auth throttles, then authentication.
        $middleware->prependToPriorityList(before: HandlePrecognitiveRequests::class, prepend: EnsureIpAllowed::class);
        // Setup gate runs before the session starts (it relaxes the Secure cookie flag on http://127.0.0.1).
        $middleware->prependToPriorityList(before: HandlePrecognitiveRequests::class, prepend: EnsureSetupComplete::class);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: ThrottleAuthFailures::class);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: ThrottleBeforeAuth::class);

        // Every web route (including Livewire's update endpoint) is IP-restricted.
        $middleware->web(prepend: [EnsureIpAllowed::class.':web', EnsureSetupComplete::class]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('catalog'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'mcp', 'mcp/*') || $request->expectsJson(),
        );
    })->create();
