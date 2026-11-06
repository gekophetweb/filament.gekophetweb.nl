<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function ssoIdentity(string $id, string $email, array $raw = []): SocialiteUser
{
    return (new SocialiteUser)->setRaw($raw)->map(['id' => $id, 'name' => 'Jane', 'email' => $email]);
}

beforeEach(function () {
    config(['services.sso.provider' => 'oidc', 'access.allowed_emails' => ['me@example.com', '@family.example']]);
});

it('requires login for every app page', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with(['/', '/spools', '/devices']);

it('shows the login page to guests', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in with SSO');
});

it('creates the user from the SSO subject and logs in', function () {
    Socialite::fake('oidc', ssoIdentity('sub-123', 'Me@Example.com'));

    $this->get('/auth/callback')->assertRedirect(route('catalog'));

    $user = User::sole();
    expect($user->sso_subject)->toBe('sub-123')->and($user->email)->toBe('me@example.com');
    $this->assertAuthenticatedAs($user);
});

it('allows whole domains', function () {
    Socialite::fake('oidc', ssoIdentity('sub-9', 'kid@family.example'));

    $this->get('/auth/callback')->assertRedirect(route('catalog'));
    $this->assertAuthenticated();
});

it('rejects identities outside the allow-list', function (string $email) {
    Socialite::fake('oidc', ssoIdentity('sub-x', $email));

    $this->get('/auth/callback')->assertRedirect('/login')->assertSessionHas('error');
    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with(['evil@example.org', 'me@example.com.evil.org', 'x@notfamily.example', 'x@sub.family.example.org']);

it('rejects unverified emails', function () {
    Socialite::fake('oidc', ssoIdentity('sub-1', 'me@example.com', ['email_verified' => false]));

    $this->get('/auth/callback')->assertRedirect('/login');
    $this->assertGuest();
});

it('rejects an empty allow-list', function () {
    config(['access.allowed_emails' => []]);
    Socialite::fake('oidc', ssoIdentity('sub-1', 'me@example.com'));

    $this->get('/auth/callback')->assertRedirect('/login');
    $this->assertGuest();
});

it('does not link a different subject to an existing email', function () {
    user(['email' => 'me@example.com', 'sso_provider' => 'oidc', 'sso_subject' => 'original']);
    Socialite::fake('oidc', ssoIdentity('attacker', 'me@example.com'));

    $this->get('/auth/callback')->assertRedirect('/login');
    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(user())->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});

it('creates an account for any verified email with open sign-up', function () {
    config(['access.allowed_emails' => ['*']]);
    Socialite::fake('oidc', ssoIdentity('sub-new', 'stranger@example.org'));

    $this->get('/auth/callback')->assertRedirect(route('catalog'));

    expect(User::sole()->email)->toBe('stranger@example.org');
    $this->assertAuthenticated();
});

it('still rejects unverified emails with open sign-up', function () {
    config(['access.allowed_emails' => ['*']]);
    Socialite::fake('oidc', ssoIdentity('sub-new', 'stranger@example.org', ['email_verified' => false]));

    $this->get('/auth/callback')->assertRedirect('/login');
    $this->assertGuest();
});
