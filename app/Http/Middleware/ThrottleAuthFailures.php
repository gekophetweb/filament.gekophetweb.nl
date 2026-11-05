<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks an IP for 10 minutes after 20 failed (401) API/MCP authentications.
 * Runs before authentication so token-guessing floods are cut cheaply.
 */
class ThrottleAuthFailures
{
    public const MAX_FAILURES = 20;

    public const DECAY_SECONDS = 600;

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'auth-failures:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILURES)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json(
                ['message' => 'Too many failed authentication attempts.', 'retry_after' => $retryAfter],
                429,
                ['Retry-After' => $retryAfter],
            );
        }

        $response = $next($request);

        if ($response->getStatusCode() === 401) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
        }

        return $response;
    }
}
