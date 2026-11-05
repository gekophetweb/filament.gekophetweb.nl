<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

it('returns 429 with Retry-After on the login limiter', function () {
    foreach (range(1, 10) as $_) {
        $this->get('/login')->assertOk();
    }

    $this->get('/login')->assertStatus(429)->assertHeader('Retry-After');
});

it('limits API reads per token and returns JSON', function () {
    Sanctum::actingAs(user(), ['spools:read']);

    foreach (range(1, 120) as $_) {
        $this->getJson('/api/v1/spools')->assertOk();
    }

    $this->getJson('/api/v1/spools')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonStructure(['message']);
});

it('limits API writes at 30 per minute', function () {
    Sanctum::actingAs(user(), ['spools:read', 'spools:write']);

    foreach (range(1, 30) as $_) {
        $this->postJson('/api/v1/spools', [])->assertStatus(422);
    }

    $this->postJson('/api/v1/spools', [])->assertStatus(429);
});

it('resets after the window', function () {
    foreach (range(1, 11) as $_) {
        $this->get('/login');
    }
    $this->get('/login')->assertStatus(429);

    $this->travel(61)->seconds();

    $this->get('/login')->assertOk();
});

it('blocks an IP after 20 failed token authentications', function () {
    foreach (range(1, 20) as $_) {
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer nope'])->assertUnauthorized();
    }

    $this->getJson('/api/v1/me', ['Authorization' => 'Bearer nope'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    // Even a valid token is refused while the IP is blocked.
    $token = User::factory()->create()->createToken('t', ['spools:read'])->plainTextToken;
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertStatus(429);

    RateLimiter::clear('auth-failures:127.0.0.1');
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertOk();
});
