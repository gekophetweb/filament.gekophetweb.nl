<?php

namespace App\Livewire;

use App\Actions\Setup\SaveSetup;
use App\Support\Setup\OidcDiscoveryCheck;
use App\Support\Setup\SetupState;
use App\Support\Setup\SsoPresets;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * First-run wizard: public address, identity provider, who may sign in,
 * a real test sign-in, then save to storage/app/.env.local.
 *
 * Draft values live in the session (the client secret encrypted), never in
 * the component snapshot, so they survive the redirect to the identity provider.
 */
#[Layout('layouts.app')]
#[Title('Set up Spool Tracker')]
class Setup extends Component
{
    public const STEPS = [1 => 'Address', 2 => 'Sign-in provider', 3 => 'Access', 4 => 'Test sign-in', 5 => 'Save'];

    public int $step = 1;

    public string $token = '';

    public string $app_url = '';

    public string $preset = 'keycloak';

    public string $tenant = '';

    public string $issuer = '';

    public string $client_id = '';

    /** Only holds a newly typed secret until the next step; then it moves to the session. */
    public string $client_secret = '';

    public string $allowed_emails = '';

    public string $ips_web = '';

    public string $ips_api = '';

    public string $ips_mcp = '';

    /** @var list<array{label: string, status: string, detail: string}> */
    public array $checks = [];

    public bool $saved = false;

    /** @var list<string> */
    public array $warnings = [];

    public function mount(Request $request, SetupState $setup): void
    {
        $token = (string) $request->query('token', '');

        if ($token !== '') {
            if ($setup->tokenMatches($token)) {
                $this->unlockSession();
            } else {
                session()->flash('setup.token_error', 'That setup link is not valid. Copy the URL from the container log again.');
            }

            // Drop the token from the address bar and browser history.
            $this->redirectRoute('setup');

            return;
        }

        foreach (session('setup.draft', []) as $field => $value) {
            if (property_exists($this, $field) && $field !== 'client_secret') {
                $this->{$field} = $value;
            }
        }

        $this->app_url = $this->app_url ?: rtrim((string) config('app.url'), '/');
        $this->step = (int) session('setup.step', 1);
    }

    public function unlock(SetupState $setup): void
    {
        $key = 'setup-token:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('token', 'Too many attempts. Wait a minute and try again.');

            return;
        }

        RateLimiter::hit($key, 60);

        if (! $setup->tokenMatches($this->token)) {
            $this->addError('token', 'That token is not valid.');

            return;
        }

        RateLimiter::clear($key);
        $this->token = '';
        $this->unlockSession();
    }

    public function next(SaveSetup $save): void
    {
        $this->authorizeSetup();

        match ($this->step) {
            1 => $this->completeAddress($save),
            2 => $this->completeProvider($save),
            3 => $this->completeAccess($save),
            4 => $this->requireTestedSignIn(),
            default => null,
        };

        if ($this->getErrorBag()->isEmpty()) {
            $this->goToStep($this->step + 1);
        }
    }

    public function back(): void
    {
        $this->authorizeSetup();
        $this->goToStep(max(1, $this->step - 1));
    }

    public function updatedPreset(): void
    {
        if (! array_key_exists($this->preset, SsoPresets::all())) {
            $this->preset = 'generic';
        }

        $this->checks = [];
        $this->issuer = SsoPresets::issuerFor($this->preset, $this->tenant) ?? '';
    }

    public function updatedTenant(): void
    {
        $this->issuer = SsoPresets::issuerFor($this->preset, $this->tenant) ?? $this->issuer;
    }

    public function checkConnection(SaveSetup $save, OidcDiscoveryCheck $discovery): void
    {
        $this->authorizeSetup();
        $this->storeSecret();

        $values = $save->validate($this->input(), ['issuer']);
        $this->checks = $discovery->run($values['issuer']);

        session(['setup.checked_issuer' => OidcDiscoveryCheck::passed($this->checks) ? $values['issuer'] : null]);
        $this->saveDraft();
    }

    public function save(SaveSetup $save): void
    {
        $this->authorizeSetup();
        $this->requireTestedSignIn();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            $this->warnings = $save->handle($this->input());
        } catch (RuntimeException $e) {
            $this->addError('save', $e->getMessage());

            return;
        }

        session()->forget(['setup.draft', 'setup.secret', 'setup.step', 'setup.checked_issuer', 'setup.tested', 'setup.unlocked']);
        $this->saved = true;
    }

    public function render(SaveSetup $save): View
    {
        return view('livewire.setup', [
            'unlocked' => (bool) session('setup.unlocked') || $this->saved,
            'presets' => SsoPresets::all(),
            'secretStored' => session()->has('setup.secret'),
            'tested' => session('setup.tested'),
            'envWritable' => $save->writer()->isWritable(),
            'publicCallback' => rtrim($this->app_url, '/').'/auth/callback',
            'testCallback' => route('setup.callback'),
            'connectionChecked' => session('setup.checked_issuer') !== null && session('setup.checked_issuer') === rtrim(trim($this->issuer), '/'),
        ]);
    }

    private function completeAddress(SaveSetup $save): void
    {
        $save->validate($this->input(), ['app_url']);

        if (! $save->writer()->isWritable()) {
            $this->addError('app_url', 'The app cannot write its settings file yet. Fix the permissions shown above, then try again.');
        }
    }

    private function completeProvider(SaveSetup $save): void
    {
        $this->storeSecret();
        $values = $save->validate($this->input(), ['issuer', 'client_id', 'client_secret']);

        if (session('setup.checked_issuer') !== $values['issuer']) {
            $this->addError('issuer', 'Run "Check connection" for this issuer first.');
        }
    }

    private function completeAccess(SaveSetup $save): void
    {
        $values = $save->validate($this->input(), ['allowed_emails', 'ips_web', 'ips_api', 'ips_mcp']);

        $this->allowed_emails = $values['allowed_emails'];
        $this->ips_web = $values['ips_web'];
        $this->ips_api = $values['ips_api'];
        $this->ips_mcp = $values['ips_mcp'];
    }

    private function requireTestedSignIn(): void
    {
        if (session('setup.tested') === null) {
            $this->addError('test', 'Sign in once with the test button before saving, so a wrong setting cannot lock you out.');
        }
    }

    private function goToStep(int $step): void
    {
        $this->step = min($step, array_key_last(self::STEPS));
        $this->saveDraft();
    }

    private function storeSecret(): void
    {
        if ($this->client_secret !== '') {
            session(['setup.secret' => Crypt::encryptString(trim($this->client_secret))]);
            $this->client_secret = '';
        }
    }

    private function saveDraft(): void
    {
        session([
            'setup.step' => $this->step,
            'setup.draft' => collect(['app_url', 'preset', 'tenant', 'issuer', 'client_id', 'allowed_emails', 'ips_web', 'ips_api', 'ips_mcp'])
                ->mapWithKeys(fn (string $field) => [$field => $this->{$field}])
                ->all(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function input(): array
    {
        $secret = session('setup.secret');

        return [
            'app_url' => $this->app_url,
            'issuer' => $this->issuer,
            'client_id' => $this->client_id,
            'client_secret' => $secret ? Crypt::decryptString($secret) : '',
            'allowed_emails' => $this->allowed_emails,
            'ips_web' => $this->ips_web,
            'ips_api' => $this->ips_api,
            'ips_mcp' => $this->ips_mcp,
        ];
    }

    private function unlockSession(): void
    {
        session()->regenerate();
        session(['setup.unlocked' => true]);
    }

    private function authorizeSetup(): void
    {
        abort_if(app(SetupState::class)->isComplete(), 404);
        abort_unless(session('setup.unlocked'), 403);
    }
}
