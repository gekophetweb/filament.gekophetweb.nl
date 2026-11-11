<?php

namespace App\Http\Middleware;

use App\Support\Setup\SetupState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('ip:web'), 'ip:api' or 'ip:mcp'.
 */
class EnsureIpAllowed
{
    public function handle(Request $request, Closure $next, string $surface = 'web'): Response
    {
        $ip = $request->ip();

        if (! $this->allowed($ip, $surface) && ! $this->isLocalMailSettingsRequest($request, $surface)) {
            Log::channel('blocked_ips')->warning('Blocked request', [
                'surface' => $surface,
                'ip' => $ip,
                'method' => $request->method(),
                'path' => $request->path(),
            ]);

            return response('Forbidden', 403, ['Content-Type' => 'text/plain']);
        }

        return $next($request);
    }

    /**
     * The mail settings page is opened on the Docker host (127.0.0.1:8000, often through an SSH tunnel), whose
     * address is rarely on the web allow-list. It checks its own one-time link, like the setup wizard.
     */
    private function isLocalMailSettingsRequest(Request $request, string $surface): bool
    {
        return $surface === 'web'
            && $request->routeIs('mail-settings', 'mail-settings.*')
            && app(SetupState::class)->isDirectLocalRequest($request);
    }

    private function allowed(?string $ip, string $surface): bool
    {
        // Before setup the web allow-list is usually still empty; EnsureSetupComplete
        // then limits the web surface to direct local requests instead.
        if ($surface === 'web' && ! app(SetupState::class)->isComplete()) {
            return true;
        }

        $ranges = config("access.ips.{$surface}", []);

        if ($ranges === []) {
            return ! app()->isProduction();
        }

        return $ip !== null && IpUtils::checkIp($ip, $ranges);
    }
}
