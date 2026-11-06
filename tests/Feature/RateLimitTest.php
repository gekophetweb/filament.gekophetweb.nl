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

it('limits API reads per user and returns JSON', function () {
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

it('shares the limit between all tokens of one user', function () {
    config(['access.rate_limits.api_read_per_minute' => 3]);
    $user = user();
    $first = $user->createToken('phone', ['spools:read'])->plainTextToken;
    $second = $user->createToken('laptop', ['spools:read'])->plainTextToken;

    $this->getJson('/api/v1/spools', ['Authorization' => "Bearer {$first}"])->assertOk();
    $this->getJson('/api/v1/spools', ['Authorization' => "Bearer {$first}"])->assertOk();
    $this->getJson('/api/v1/spools', ['Authorization' => "Bearer {$second}"])->assertOk();

    $this->getJson('/api/v1/spools', ['Authorization' => "Bearer {$second}"])->assertStatus(429);
});

it('keeps each user on their own limit', function () {
    config(['access.rate_limits.api_read_per_minute' => 1]);

    Sanctum::actingAs(user(), ['spools:read']);
    $this->getJson('/api/v1/spools')->assertOk();

    Sanctum::actingAs(user(), ['spools:read']);
    $this->getJson('/api/v1/spools')->assertOk();
});

it('caps API writes per day, even when the minute window has passed', function () {
    config(['access.rate_limits.api_write_per_day' => 2]);
    Sanctum::actingAs(user(), ['spools:read', 'spools:write']);

    $this->postJson('/api/v1/spools', [])->assertStatus(422);
    $this->postJson('/api/v1/spools', [])->assertStatus(422);

    $this->travel(2)->minutes();
    $this->postJson('/api/v1/spools', [])->assertStatus(429);

    $this->travel(1)->days();
    $this->postJson('/api/v1/spools', [])->assertStatus(422);
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
