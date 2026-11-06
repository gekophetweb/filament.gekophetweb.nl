<?php

namespace App\Providers;

use App\Support\Sso\OidcProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\SocialiteManager;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureSso();
        $this->configureRateLimiting();
    }

    private function configureSso(): void
    {
        $sso = config('services.sso');

        // Built-in Socialite drivers read services.<driver>; mirror the SSO config there.
        config(["services.{$sso['provider']}" => array_merge(config("services.{$sso['provider']}", []), [
            'client_id' => $sso['client_id'],
            'client_secret' => $sso['client_secret'],
            'redirect' => $sso['redirect'],
        ])]);

        // Read at resolve time, so the setup wizard can test unsaved settings.
        $this->app->afterResolving(Socialite::class, function (SocialiteManager $manager): void {
            $manager->extend('oidc', fn () => $manager
                ->buildProvider(OidcProvider::class, config('services.oidc'))
                ->setIssuer((string) config('services.sso.issuer')));
        });
    }

    private function configureRateLimiting(): void
    {
        $tokenKey = function (Request $request): string {
            $token = $request->user()?->currentAccessToken();

            return $token instanceof PersonalAccessToken ? 'token:'.$token->getKey() : 'ip:'.$request->ip();
        };

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('sso-callback', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('token-exchange', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));

        // Cheap IP throttle that runs before token authentication.
        RateLimiter::for('api-ip', fn (Request $r) => Limit::perMinute(300)->by($r->ip()));

        RateLimiter::for('api-read', fn (Request $r) => Limit::perMinute(120)->by($tokenKey($r)));
        RateLimiter::for('api-write', fn (Request $r) => Limit::perMinute(30)->by($tokenKey($r)));
        RateLimiter::for('mcp', fn (Request $r) => Limit::perMinute(60)->by($tokenKey($r)));

        RateLimiter::for('web', fn (Request $r) => Limit::perMinute(120)->by(
            $r->user() ? 'user:'.$r->user()->getAuthIdentifier() : 'ip:'.$r->ip()
        ));
    }
}
