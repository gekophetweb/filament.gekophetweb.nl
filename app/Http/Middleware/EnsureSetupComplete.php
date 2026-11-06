<?php

namespace App\Http\Middleware;

use App\Support\Setup\SetupState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until setup is complete, every web page redirects to the setup wizard and
 * only direct local requests get through. Afterwards the wizard is gone (404).
 */
class EnsureSetupComplete
{
    public function __construct(private SetupState $setup) {}

    public function handle(Request $request, Closure $next): Response
    {
        $isSetupRoute = $request->routeIs('setup', 'setup.*');

        if ($this->setup->isComplete()) {
            abort_if($isSetupRoute, 404);

            return $next($request);
        }

        if (! $this->setup->isDirectLocalRequest($request)) {
            return response('This app has not been set up yet.', 503, ['Content-Type' => 'text/plain', 'Retry-After' => '300']);
        }

        // The wizard runs on http://127.0.0.1:8000, where a Secure-only
        // session cookie would not be sent back by every browser.
        config(['session.secure' => $request->isSecure()]);

        if (! $isSetupRoute && ! $request->is('livewire*')) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
