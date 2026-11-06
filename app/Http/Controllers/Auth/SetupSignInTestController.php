<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResolveSsoUser;
use App\Actions\Setup\SaveSetup;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteManager;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Setup step 4: a real sign-in round trip with the unsaved draft settings.
 * It only proves the settings work; no user is created or logged in.
 */
class SetupSignInTestController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        $this->authorizeSetup();

        if (blank(session('setup.checked_issuer'))) {
            return redirect()->route('setup')->with('setup.test_error', 'Run "Check connection" in step 2 first.');
        }

        return $this->provider()->redirect();
    }

    public function callback(): RedirectResponse
    {
        $this->authorizeSetup();

        try {
            $identity = $this->provider()->user();
        } catch (Throwable $e) {
            Log::warning('Setup test sign-in failed', ['error' => $e->getMessage()]);

            return redirect()->route('setup')->with('setup.test_error', 'Test sign-in failed: '.$e->getMessage());
        }

        $email = strtolower((string) $identity->getEmail());
        $raw = method_exists($identity, 'getRaw') ? $identity->getRaw() : [];

        if ($email === '' || ($raw['email_verified'] ?? true) === false) {
            return redirect()->route('setup')->with('setup.test_error', 'The provider did not return a verified email address. Check the "email" scope and the account.');
        }

        $draft = session('setup.draft', []);
        $allowList = SaveSetup::splitList((string) ($draft['allowed_emails'] ?? ''));

        if (! ResolveSsoUser::matchesAllowList($email, $allowList)) {
            $draft['allowed_emails'] = implode(',', [...$allowList, $email]);
            session()->flash('setup.notice', "{$email} was added to the allowed accounts, so you can sign in after saving.");
        }

        session([
            'setup.draft' => $draft,
            'setup.tested' => ['email' => $email, 'name' => $identity->getName() ?: $email],
            'setup.step' => 5,
        ]);

        return redirect()->route('setup');
    }

    /**
     * The regular OIDC driver, pointed at the draft settings for this request
     * only and redirecting back to the local test callback.
     */
    private function provider(): Provider
    {
        $draft = session('setup.draft', []);
        $secret = session('setup.secret');

        config([
            'services.sso.issuer' => (string) ($draft['issuer'] ?? ''),
            'services.oidc' => [
                'client_id' => (string) ($draft['client_id'] ?? ''),
                'client_secret' => $secret ? Crypt::decryptString($secret) : '',
                'redirect' => route('setup.callback'),
            ],
        ]);

        // Drop a driver built earlier with the saved config (Octane reuses the manager).
        $manager = Socialite::getFacadeRoot();
        if ($manager instanceof SocialiteManager) {
            $manager->forgetDrivers();
        }

        return Socialite::driver('oidc');
    }

    private function authorizeSetup(): void
    {
        abort_unless(session('setup.unlocked'), 403);
    }
}
