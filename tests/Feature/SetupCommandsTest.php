<?php

use App\Support\Setup\SetupState;
use App\Support\Setup\SsoPresets;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/setup-cmd-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->dir);
    file_put_contents($this->dir.'/.env', "SSO_CLIENT_ID=\n");

    config([
        'services.sso.client_id' => null,
        'setup.lock_path' => $this->dir.'/setup.lock',
        'setup.token_path' => $this->dir.'/setup.token',
        'setup.env_path' => $this->dir.'/.env',
        'setup.backup_path' => $this->dir.'/backups',
    ]);
});

afterEach(fn () => File::deleteDirectory($this->dir));

it('prints the setup link with a token until setup is complete', function () {
    $this->artisan('setup:url')
        ->expectsOutputToContain('Setup URL: http://127.0.0.1:8000/setup?token=')
        ->assertSuccessful();

    expect(app(SetupState::class)->tokenMatches((string) file_get_contents($this->dir.'/setup.token')))->toBeTrue();
});

it('prints nothing once setup is complete and nothing is missing', function () {
    config(['mail.default' => 'smtp', 'mail.from.address' => 'spools@gekophetweb.nl', 'mail.mailers.smtp.dkim.domain' => 'gekophetweb.nl']);
    app(SetupState::class)->complete();

    $this->artisan('setup:url')->doesntExpectOutputToContain('Setup URL')->doesntExpectOutputToContain('missing')->assertSuccessful();
});

it('lists missing settings of a set-up app with a link to fix them', function () {
    config([
        'mail.default' => 'log',
        'mail.from.address' => 'hello@example.com',
        'setup.mail_token_path' => $this->dir.'/mail-settings.token',
    ]);
    app(SetupState::class)->complete();

    $this->artisan('setup:url')
        ->expectsOutputToContain('some settings are missing')
        ->expectsOutputToContain('Email only goes to the log')
        ->expectsOutputToContain('still the example address hello@example.com')
        ->expectsOutputToContain('Mail settings URL: http://127.0.0.1:8000/mail-settings?token=')
        ->doesntExpectOutputToContain('Setup URL')
        ->assertSuccessful();

    expect(file_exists($this->dir.'/mail-settings.token'))->toBeTrue();
});

it('mentions a tip without putting a link in the log', function () {
    config([
        'mail.default' => 'smtp',
        'mail.from.address' => 'spools@gekophetweb.nl',
        'mail.mailers.smtp.dkim.domain' => null,
        'setup.mail_token_path' => $this->dir.'/mail-settings.token',
    ]);
    app(SetupState::class)->complete();

    $this->artisan('setup:url')
        ->expectsOutputToContain('Tip: Email is sent without a DKIM signature')
        ->doesntExpectOutputToContain('Mail settings URL')
        ->assertSuccessful();

    expect(file_exists($this->dir.'/mail-settings.token'))->toBeFalse();
});

it('configures SSO from the terminal', function () {
    Process::fake();
    Http::preventStrayRequests();
    Http::fake(['https://accounts.google.com/.well-known/openid-configuration' => Http::response([
        'issuer' => 'https://accounts.google.com',
        'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_endpoint' => 'https://oauth2.googleapis.com/token',
        'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
        'code_challenge_methods_supported' => ['plain', 'S256'],
    ])]);

    $this->artisan('sso:setup')
        ->expectsQuestion('Public address of the app', 'https://spools.example.com')
        ->expectsChoice('Sign-in provider', 'google', array_map(fn (array $preset) => "{$preset['label']} — {$preset['summary']}", SsoPresets::all()))
        ->expectsQuestion('Client ID', 'google-client')
        ->expectsQuestion('Client secret', 'g-secret')
        ->expectsChoice('Who may use the app?', 'listed', ['Anyone (open sign-up): every account at the provider, from any network', 'Only people I list: the accounts and networks I enter next', 'everyone', 'listed'])
        ->expectsQuestion('Allowed accounts', '@family.example')
        ->expectsQuestion('IP ranges for the website', '192.168.1.0/24')
        ->expectsQuestion('IP ranges for the Android app API', '')
        ->expectsQuestion('IP ranges for the MCP server', '')
        ->expectsConfirmation('Write these settings to storage/app/.env.local?', 'yes')
        ->assertSuccessful();

    expect(file_get_contents($this->dir.'/.env'))
        ->toContain("SSO_ISSUER='https://accounts.google.com'")
        ->toContain("SSO_CLIENT_ID='google-client'")
        ->toContain("SSO_ALLOWED_EMAILS='@family.example'");
    expect(app(SetupState::class)->isComplete())->toBeTrue();
});

it('opens sign-up to everyone from the terminal without asking for lists', function () {
    Process::fake();
    Http::preventStrayRequests();
    Http::fake(['https://accounts.google.com/.well-known/openid-configuration' => Http::response([
        'issuer' => 'https://accounts.google.com',
        'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_endpoint' => 'https://oauth2.googleapis.com/token',
        'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
        'code_challenge_methods_supported' => ['S256'],
    ])]);

    $this->artisan('sso:setup')
        ->expectsQuestion('Public address of the app', 'https://spools.example.com')
        ->expectsChoice('Sign-in provider', 'google', array_map(fn (array $preset) => "{$preset['label']} — {$preset['summary']}", SsoPresets::all()))
        ->expectsQuestion('Client ID', 'google-client')
        ->expectsQuestion('Client secret', 'g-secret')
        ->expectsChoice('Who may use the app?', 'everyone', ['Anyone (open sign-up): every account at the provider, from any network', 'Only people I list: the accounts and networks I enter next', 'everyone', 'listed'])
        ->expectsConfirmation('Write these settings to storage/app/.env.local?', 'yes')
        ->assertSuccessful();

    expect(file_get_contents($this->dir.'/.env'))
        ->toContain("SSO_ALLOWED_EMAILS='*'")
        ->toContain("ALLOWED_IPS_WEB='*'")
        ->toContain("ALLOWED_IPS_API='*'")
        ->toContain("ALLOWED_IPS_MCP='*'");
});

it('refuses to run once setup is complete', function () {
    app(SetupState::class)->complete();

    $this->artisan('sso:setup')->assertFailed();
    expect(file_get_contents($this->dir.'/.env'))->toBe("SSO_CLIENT_ID=\n");
});
