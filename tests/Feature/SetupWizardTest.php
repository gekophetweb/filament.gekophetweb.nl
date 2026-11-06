<?php

use App\Livewire\Setup;
use App\Support\Setup\OidcDiscoveryCheck;
use App\Support\Setup\SetupState;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

const ISSUER = 'https://id.example.com';

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/setup-test-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->dir);
    file_put_contents($this->dir.'/.env', "APP_NAME=Spools\nSSO_CLIENT_ID=\n");

    // A fresh install: no SSO configured, no lock, wizard files in a temp dir.
    config([
        'services.sso.client_id' => null,
        'setup.lock_path' => $this->dir.'/setup.lock',
        'setup.token_path' => $this->dir.'/setup.token',
        'setup.env_path' => $this->dir.'/.env',
        'setup.backup_path' => $this->dir.'/backups',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

function discoveryDocument(array $overrides = []): array
{
    return array_merge([
        'issuer' => ISSUER,
        'authorization_endpoint' => ISSUER.'/auth',
        'token_endpoint' => ISSUER.'/token',
        'userinfo_endpoint' => ISSUER.'/userinfo',
        'code_challenge_methods_supported' => ['S256'],
    ], $overrides);
}

function fakeDiscovery(array|int $document): void
{
    Http::preventStrayRequests();
    Http::fake([ISSUER.'/.well-known/openid-configuration' => is_int($document) ? Http::response('', $document) : Http::response($document)]);
}

/** A session as it is after steps 1-3 have passed. */
function unlockedDraft(array $draft = [], bool $tested = true): void
{
    session([
        'setup.unlocked' => true,
        'setup.step' => 5,
        'setup.checked_issuer' => ISSUER,
        'setup.secret' => Crypt::encryptString('s3cret$value'),
        'setup.draft' => array_merge([
            'app_url' => 'https://spools.example.com',
            'preset' => 'keycloak',
            'tenant' => '',
            'issuer' => ISSUER,
            'client_id' => 'client-1',
            'allowed_emails' => 'me@example.com',
            'ips_web' => '192.168.1.0/24',
            'ips_api' => '',
            'ips_mcp' => '',
        ], $draft),
        'setup.tested' => $tested ? ['email' => 'me@example.com', 'name' => 'Me'] : null,
    ]);
}

describe('gate', function () {
    it('sends every page to the wizard until setup is done', function () {
        $this->get('/')->assertRedirect(route('setup'));
        $this->get('/login')->assertRedirect(route('setup'));
    });

    it('refuses requests that come through a proxy or tunnel', function (string $header) {
        $this->withHeaders([$header => '203.0.113.9'])->get('/setup')->assertStatus(503);
        $this->withHeaders([$header => '203.0.113.9'])->get('/')->assertStatus(503);
    })->with(['CF-Connecting-IP', 'X-Forwarded-For']);

    it('refuses public remote addresses', function () {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/setup')->assertStatus(503);
    });

    it('shows the token form locally, even while the web allow-list is empty in production', function () {
        config(['access.ips.web' => []]);
        app()->detectEnvironment(fn () => 'production');

        $this->get('/setup')->assertOk()->assertSee('Enter the setup token');
    });

    it('closes the wizard once the lock file exists', function () {
        app(SetupState::class)->complete();

        $this->get('/setup')->assertNotFound();
        $this->get('/')->assertRedirect('/login');
    });

    it('keeps the wizard closed on installs where SSO was configured by hand', function () {
        config(['services.sso.client_id' => 'existing-client']);

        $this->get('/setup')->assertNotFound();
    });
});

describe('token', function () {
    it('unlocks with the token from the log and removes it from the address bar', function () {
        $token = app(SetupState::class)->token();

        $this->get('/setup?token='.$token)->assertRedirect(route('setup'));

        expect(session('setup.unlocked'))->toBeTrue();
    });

    it('stays locked with a wrong token in the link', function () {
        app(SetupState::class)->token();

        $this->get('/setup?token=wrong')->assertRedirect(route('setup'));

        expect(session('setup.unlocked'))->toBeNull();
    });

    it('unlocks with a pasted token', function () {
        $token = app(SetupState::class)->token();

        Livewire::test(Setup::class)->set('token', $token)->call('unlock')->assertHasNoErrors();

        expect(session('setup.unlocked'))->toBeTrue();
    });

    it('limits token guesses', function () {
        $token = app(SetupState::class)->token();
        $component = Livewire::test(Setup::class);

        foreach (range(1, 5) as $attempt) {
            $component->set('token', 'wrong-'.$attempt)->call('unlock');
        }

        $component->set('token', $token)->call('unlock')
            ->assertHasErrors(['token' => 'Too many attempts. Wait a minute and try again.']);
        expect(session('setup.unlocked'))->toBeNull();
    });

    it('refuses wizard actions before unlocking', function (string $action) {
        Livewire::test(Setup::class)->call($action)->assertForbidden();
    })->with(['next', 'back', 'checkConnection', 'save']);
});

describe('steps', function () {
    beforeEach(fn () => session(['setup.unlocked' => true]));

    it('requires a public address without a path', function () {
        Livewire::test(Setup::class)
            ->set('app_url', 'https://spools.example.com/app')
            ->call('next')
            ->assertHasErrors(['app_url' => 'Use only the scheme and host, without a path or query.'])
            ->assertSet('step', 1);
    });

    it('stops at step 1 when the settings file cannot be written', function () {
        config(['setup.env_path' => $this->dir.'/missing/.env']);

        Livewire::test(Setup::class)
            ->set('app_url', 'https://spools.example.com')
            ->call('next')
            ->assertHasErrors('app_url')
            ->assertSee('The app cannot write');
    });

    it('checks the issuer and keeps the secret out of the component', function () {
        fakeDiscovery(discoveryDocument());

        Livewire::test(Setup::class)
            ->set('step', 2)
            ->set('issuer', ISSUER.'/')
            ->set('client_id', 'client-1')
            ->set('client_secret', 'top-secret')
            ->call('checkConnection')
            ->assertSet('client_secret', '')
            ->assertSet('checks.0.status', 'ok')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 3);

        expect(session('setup.checked_issuer'))->toBe(ISSUER)
            ->and(Crypt::decryptString(session('setup.secret')))->toBe('top-secret');
    });

    it('builds the issuer from the provider domain and shows where to find each value', function () {
        Livewire::test(Setup::class)
            ->set(['step' => 2, 'app_url' => 'https://spools.example.com'])
            ->set('preset', 'okta')
            ->set('tenant', ' https://dev-123.okta.com/ ')
            ->assertSet('issuer', 'https://dev-123.okta.com')
            ->assertSee('Create the client in Okta')
            ->assertSee('Add both redirect URIs below as &quot;Sign-in redirect URIs&quot;.', false)
            ->assertSee('General tab → Client Credentials → Client ID.')
            ->assertSee('https://developer.okta.com/docs/guides/sign-into-web-app-redirect/');
    });

    it('fills both redirect URIs into a provider configuration snippet', function () {
        Livewire::test(Setup::class)
            ->set(['step' => 2, 'app_url' => 'https://spools.example.com'])
            ->set('preset', 'authelia')
            ->assertSee("- 'https://spools.example.com/auth/callback'")
            ->assertSee("- '".route('setup.callback')."'");
    });

    it('falls back to the generic provider for an unknown choice', function () {
        Livewire::test(Setup::class)
            ->set('step', 2)
            ->set('preset', 'nonexistent')
            ->assertSet('preset', 'generic')
            ->assertOk();
    });

    it('does not continue past step 2 when the issuer check fails', function () {
        fakeDiscovery(404);

        Livewire::test(Setup::class)
            ->set('step', 2)
            ->set('issuer', ISSUER)
            ->set('client_id', 'client-1')
            ->set('client_secret', 'top-secret')
            ->call('checkConnection')
            ->assertSet('checks.0.status', 'fail')
            ->call('next')
            ->assertHasErrors(['issuer' => 'Run "Check connection" for this issuer first.'])
            ->assertSet('step', 2);
    });

    it('validates who may sign in', function (string $field, string $value, string $message) {
        Livewire::test(Setup::class)
            ->set('step', 3)
            ->set('audience', 'listed')
            ->set(['allowed_emails' => 'me@example.com', 'ips_web' => '192.168.1.0/24'])
            ->set($field, $value)
            ->call('next')
            ->assertHasErrors([$field => $message])
            ->assertSet('step', 3);
    })->with([
        'no accounts' => ['allowed_emails', '', 'Add at least one email address or @domain, otherwise nobody can sign in.'],
        'bad email' => ['allowed_emails', 'me@example.com, nope', '"nope" is not an email address or @domain.'],
        'bad domain' => ['allowed_emails', '@localhost', '"@localhost" is not an email address or @domain.'],
        'no web ranges' => ['ips_web', '', 'Add at least one IP range; an empty list blocks every browser in production.'],
        'bad address' => ['ips_web', '300.1.1.1', '"300.1.1.1" is not an IP address or CIDR range.'],
        'bad prefix' => ['ips_api', '10.0.0.0/33', '"10.0.0.0/33" is not an IP address or CIDR range.'],
    ]);

    it('normalizes the access lists', function () {
        Livewire::test(Setup::class)
            ->set('step', 3)
            ->set('audience', 'listed')
            ->set(['allowed_emails' => " Me@Example.com ;\n@family.example, me@example.com ", 'ips_web' => '192.168.1.0/24  2001:db8::/32'])
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('allowed_emails', 'me@example.com,@family.example')
            ->assertSet('ips_web', '192.168.1.0/24,2001:db8::/32');
    });

    it('opens sign-up and every network to everyone by default', function () {
        Livewire::test(Setup::class)
            ->set('step', 3)
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 4)
            ->assertSet('allowed_emails', '*')
            ->assertSet('ips_web', '*')
            ->assertSet('ips_api', '*')
            ->assertSet('ips_mcp', '*');
    });

    it('clears the open values when switching to a list', function () {
        Livewire::test(Setup::class)
            ->set(['step' => 3, 'allowed_emails' => '*', 'ips_web' => '*', 'ips_api' => '*', 'ips_mcp' => '*'])
            ->set('audience', 'listed')
            ->assertSet('allowed_emails', '')
            ->assertSet('ips_web', '')
            ->call('next')
            ->assertHasErrors(['allowed_emails', 'ips_web']);
    });

    it('reduces a list that contains * to *', function () {
        Livewire::test(Setup::class)
            ->set('step', 3)
            ->set('audience', 'listed')
            ->set(['allowed_emails' => 'me@example.com, *', 'ips_web' => '192.168.1.0/24, *'])
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('allowed_emails', '*')
            ->assertSet('ips_web', '*');
    });

    it('needs a successful test sign-in to leave step 4', function () {
        Livewire::test(Setup::class)->set('step', 4)->call('next')->assertHasErrors('test')->assertSet('step', 4);
    });
});

describe('discovery check', function () {
    it('reports problems in the discovery document', function (array $overrides, int $index, string $status) {
        fakeDiscovery(discoveryDocument($overrides));

        $checks = app(OidcDiscoveryCheck::class)->run(ISSUER);

        expect($checks[$index]['status'])->toBe($status)
            ->and(OidcDiscoveryCheck::passed($checks))->toBe($status !== 'fail');
    })->with([
        'other issuer' => [['issuer' => 'https://other.example.com'], 1, 'warn'],
        'no userinfo endpoint' => [['userinfo_endpoint' => null], 2, 'fail'],
        'PKCE not advertised' => [['code_challenge_methods_supported' => null], 3, 'warn'],
        'no S256' => [['code_challenge_methods_supported' => ['plain']], 3, 'fail'],
    ]);
});

describe('test sign-in', function () {
    function identity(string $email, array $raw = []): SocialiteUser
    {
        return (new SocialiteUser)->setRaw($raw)->map(['id' => 'sub-1', 'name' => 'Me', 'email' => $email]);
    }

    it('signs in with the draft settings and the local callback', function () {
        unlockedDraft(tested: false);
        Socialite::fake('oidc', identity('me@example.com'));

        $this->get('/setup/test')->assertRedirect();

        expect(config('services.oidc.redirect'))->toBe(route('setup.callback'))
            ->and(config('services.oidc.client_secret'))->toBe('s3cret$value')
            ->and(config('services.sso.issuer'))->toBe(ISSUER);
    });

    it('records the tested account and adds it to the allow-list', function () {
        unlockedDraft(['allowed_emails' => 'other@example.com'], tested: false);
        Socialite::fake('oidc', identity('Me@Example.com'));

        $this->get('/setup/callback')->assertRedirect(route('setup'))->assertSessionHas('setup.notice');

        expect(session('setup.tested.email'))->toBe('me@example.com')
            ->and(session('setup.draft.allowed_emails'))->toBe('other@example.com,me@example.com')
            ->and(session('setup.step'))->toBe(5);
    });

    it('refuses an unverified email', function () {
        unlockedDraft(tested: false);
        Socialite::fake('oidc', identity('me@example.com', ['email_verified' => false]));

        $this->get('/setup/callback')->assertRedirect(route('setup'))->assertSessionHas('setup.test_error');

        expect(session('setup.tested'))->toBeNull();
    });

    it('refuses the test sign-in before unlocking', function (string $url) {
        $this->get($url)->assertForbidden();
    })->with(['/setup/test', '/setup/callback']);

    it('refuses the test sign-in before the issuer was checked', function () {
        unlockedDraft(tested: false);
        session()->forget('setup.checked_issuer');

        $this->get('/setup/test')->assertRedirect(route('setup'))->assertSessionHas('setup.test_error', 'Run "Check connection" in step 2 first.');
    });
});

describe('save', function () {
    it('writes the settings to .env, closes the wizard and reloads the app', function () {
        Process::fake();
        app(SetupState::class)->token();
        unlockedDraft();

        Livewire::test(Setup::class)->call('save')->assertHasNoErrors()->assertSet('saved', true)->assertSee('Setup complete');

        expect(file_get_contents($this->dir.'/.env'))
            ->toContain("SSO_CLIENT_ID='client-1'")
            ->toContain("SSO_CLIENT_SECRET='s3cret\$value'")
            ->toContain("SSO_REDIRECT_URI='https://spools.example.com/auth/callback'")
            ->toContain("SSO_ALLOWED_EMAILS='me@example.com'")
            ->toContain("ALLOWED_IPS_WEB='192.168.1.0/24'")
            ->toContain('APP_NAME=Spools');
        expect(app(SetupState::class)->isComplete())->toBeTrue()
            ->and(file_exists($this->dir.'/setup.token'))->toBeFalse()
            ->and(session('setup.secret'))->toBeNull();
        Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), 'artisan config:clear'));
        Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), 'artisan queue:restart'));
    });

    it('does not save without a successful test sign-in', function () {
        Process::fake();
        unlockedDraft(tested: false);

        Livewire::test(Setup::class)->call('save')->assertHasErrors('test')->assertSet('saved', false);

        expect(file_get_contents($this->dir.'/.env'))->toBe("APP_NAME=Spools\nSSO_CLIENT_ID=\n")
            ->and(app(SetupState::class)->isComplete())->toBeFalse();
        Process::assertNothingRan();
    });

    it('reports a settings file that cannot be written', function () {
        Process::fake();
        unlockedDraft();
        config(['setup.env_path' => $this->dir.'/missing/.env']);

        Livewire::test(Setup::class)->call('save')->assertHasErrors(['save' => 'The app cannot write '.$this->dir.'/missing/.env.']);

        expect(app(SetupState::class)->isComplete())->toBeFalse();
    });

    it('escapes values shown on the review page', function () {
        unlockedDraft(['client_id' => '<script>alert(1)</script>']);

        Livewire::test(Setup::class)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    });
});
