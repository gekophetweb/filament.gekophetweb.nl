<?php

namespace App\Providers;

use App\Support\Mail\DkimSigningTransport;
use App\Support\PrintRequester;
use App\Support\Sso\OidcProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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
        $this->configureDkim();
    }

    /**
     * SMTP mailers sign with DKIM when their "dkim" config is filled in; the transport itself is Laravel's own.
     */
    private function configureDkim(): void
    {
        $this->callAfterResolving('mail.manager', function (MailManager $manager): void {
            $manager->extend('smtp', function (array $config) use ($manager) {
                $smtp = (fn (array $config) => $this->createSmtpTransport($config))->call($manager, $config);

                return DkimSigningTransport::wrap($smtp, $config['dkim'] ?? []);
            });
        });
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
        // Per user, not per token: with open sign-up, extra tokens must not add up to extra quota.
        $userKey = fn (Request $request): string => $request->user()
            ? 'user:'.$request->user()->getAuthIdentifier()
            : 'ip:'.$request->ip();
        // Read on each request, so config/access.php is the single place for these numbers.
        $limit = fn (string $name): int => (int) config("access.rate_limits.{$name}");

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('sso-callback', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('token-exchange', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('share', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        // Per signed-in requester when there is one, so a requester cannot get around it by changing networks.
        RateLimiter::for('print-request', fn (Request $r) => Limit::perHour($limit('print_requests_per_hour'))
            ->by(PrintRequester::current() ? 'requester:'.PrintRequester::current()->email : 'ip:'.$r->ip()));
        RateLimiter::for('suggestion', fn (Request $r) => Limit::perHour($limit('suggestions_per_hour'))
            ->by(PrintRequester::current() ? 'requester:'.PrintRequester::current()->email : 'ip:'.$r->ip()));
        // Emailed sign-in codes, so the share link cannot be used to flood someone's inbox.
        RateLimiter::for('print-request-code', fn (Request $r) => [
            Limit::perHour($limit('print_request_codes_per_hour'))->by('ip:'.$r->ip()),
            Limit::perHour($limit('print_request_codes_per_hour'))->by('email:'.strtolower((string) $r->input('email'))),
        ]);
        RateLimiter::for('docs', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));

        // Cheap IP throttle that runs before token authentication.
        RateLimiter::for('api-ip', fn (Request $r) => Limit::perMinute($limit('api_ip_per_minute'))->by($r->ip()));

        RateLimiter::for('api-read', fn (Request $r) => Limit::perMinute($limit('api_read_per_minute'))->by($userKey($r)));
        RateLimiter::for('api-write', fn (Request $r) => [
            Limit::perMinute($limit('api_write_per_minute'))->by('minute:'.$userKey($r)),
            Limit::perDay($limit('api_write_per_day'))->by('day:'.$userKey($r)),
        ]);
        RateLimiter::for('mcp', fn (Request $r) => Limit::perMinute($limit('mcp_per_minute'))->by($userKey($r)));

        RateLimiter::for('web', fn (Request $r) => Limit::perMinute(120)->by($userKey($r)));
    }
}
