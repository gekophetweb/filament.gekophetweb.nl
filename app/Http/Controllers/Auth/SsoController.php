<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveSsoUser;
use App\Http\Controllers\Controller;
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

    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver($this->provider())->redirect();
    }

    public function callback(Request $request, ResolveSsoUser $resolve): RedirectResponse
    {
        try {
            $identity = Socialite::driver($this->provider())->user();
        } catch (Throwable $e) {
            Log::warning('SSO callback failed', ['error' => $e->getMessage(), 'ip' => $request->ip()]);

            return redirect()->route('login')->with('error', 'Sign-in failed. Please try again.');
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
