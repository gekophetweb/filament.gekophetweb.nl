<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveSsoUser;
use App\Http\Controllers\Controller;
use App\Support\PrintRequester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class SsoController extends Controller
{
    public function login(): View
    {
        return view('auth.login');
    }

    public function redirect(Request $request): SymfonyRedirect
    {
        // A print requester sign-in that was started but never finished must not take over this one.
        $request->session()->forget(PrintRequester::PENDING_SESSION_KEY);

        return Socialite::driver($this->provider())->redirect();
    }

    /**
     * The one callback URL registered at the identity provider, for app logins and print requester sign-ins.
     */
    public function callback(Request $request, ResolveSsoUser $resolve): RedirectResponse
    {
        $requesterToken = $request->session()->pull(PrintRequester::PENDING_SESSION_KEY);

        try {
            $identity = Socialite::driver($this->provider())->user();
        } catch (Throwable $e) {
            Log::warning('SSO callback failed', ['error' => $e->getMessage(), 'ip' => $request->ip()]);

            return is_string($requesterToken)
                ? redirect()->route(PrintRequester::pageRoute($request->session()->pull(PrintRequester::RETURN_SESSION_KEY)), $requesterToken)->with('print_requester_error', 'Sign-in failed. Please try again.')
                : redirect()->route('login')->with('error', 'Sign-in failed. Please try again.');
        }

        if (is_string($requesterToken)) {
            return PrintRequesterSignInController::complete($request, $requesterToken, $identity);
        }

        $user = $resolve->handle($this->provider(), $identity);

        if ($user === null) {
            Log::warning('SSO login denied', ['email' => $identity->getEmail(), 'ip' => $request->ip()]);

            return redirect()->route('login')->with('error', 'This account is not allowed to sign in.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('catalog'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function provider(): string
    {
        return config('services.sso.provider');
    }
}
