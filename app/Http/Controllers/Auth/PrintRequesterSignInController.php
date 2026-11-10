<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveSsoUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PrintRequester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Signing in to send print requests through a share link. Uses the app's SSO provider and its
 * regular callback URL, but only remembers the verified email in the session: no account is made.
 */
class PrintRequesterSignInController extends Controller
{
    public function redirect(Request $request, string $token): SymfonyRedirect
    {
        $owner = User::where('share_token', $token)->firstOrFail();
        abort_unless($owner->takesPrintRequests(), 404);

        // Already known (signed in to the app, or as a requester before): nothing to do.
        if (PrintRequester::current() !== null) {
            return redirect()->route('share', $token);
        }

        $request->session()->put(PrintRequester::PENDING_SESSION_KEY, $token);

        return Socialite::driver(config('services.sso.provider'))->redirect();
    }

    /**
     * Finish the SSO round trip that {@see redirect()} started; called from the regular SSO callback.
     */
    public static function complete(Request $request, string $token, SocialiteUser $identity): RedirectResponse
    {
        $back = redirect()->route('share', $token);
        $email = ResolveSsoUser::verifiedEmail($identity);

        if ($email === null) {
            Log::warning('Print requester sign-in without a verified email', ['ip' => $request->ip()]);

            return $back->with('print_requester_error', 'Your account has no verified email address, so you cannot sign in to send a request.');
        }

        $request->session()->regenerate();
        (new PrintRequester($email, $identity->getName() ?: Str::before($email, '@')))->remember();

        return $back;
    }

    public function signOut(Request $request, string $token): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('share', $token);
        }

        PrintRequester::forget();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('share', $token);
    }
}
