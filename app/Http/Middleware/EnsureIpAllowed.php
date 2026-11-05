<?php

namespace App\Http\Middleware;

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

        if (! $this->allowed($ip, $surface)) {
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

    private function allowed(?string $ip, string $surface): bool
    {
        $ranges = config("access.ips.{$surface}", []);

        if ($ranges === []) {
            return ! app()->isProduction();
        }

        return $ip !== null && IpUtils::checkIp($ip, $ranges);
    }
}
