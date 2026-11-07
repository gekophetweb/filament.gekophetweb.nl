<?php

use App\Enums\SpoolStatus;
use App\Models\FilamentProduct;
use App\Models\Spool;
use Laravel\Sanctum\Sanctum;

it('rejects requests without a token', function () {
    $this->getJson('/api/v1/spools')->assertUnauthorized();
});

it('returns the current user and token abilities', function () {
    $user = user();
    $token = $user->createToken('phone', ['spools:read'])->plainTextToken;

    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.abilities', ['spools:read']);
});

it('filters the catalog and caps per_page', function () {
    Sanctum::actingAs(user(), ['spools:read']);
    FilamentProduct::factory()->color('#0A2989')->create(['article_name' => 'Cobalt Blue']);
    FilamentProduct::factory()->color('#C12E1F')->create(['article_name' => 'Scarlet Red']);

    $this->getJson('/api/v1/catalog?color=blue')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.article_name', 'Cobalt Blue')
        ->assertJsonPath('data.0.owned_count', 0);

    $this->getJson('/api/v1/catalog?per_page=500')->assertStatus(422);
});

it('orders the catalog by article number', function () {
    Sanctum::actingAs(user(), ['spools:read']);
    foreach (['10300', '10100', '11101'] as $articleNumber) {
        FilamentProduct::factory()->create(['article_number' => $articleNumber]);
    }

    expect($this->getJson('/api/v1/catalog')->assertOk()->json('data.*.article_number'))
        ->toBe(['10100', '10300', '11101']);
});

it('shows boxed and on-spool counts and a buy link in the catalog', function () {
    $user = user();
    $product = FilamentProduct::factory()->create(['article_number' => '10100']);
    Spool::factory()->for($user)->for($product, 'product')->count(2)->create();
    Spool::factory()->for($user)->for($product, 'product')->onSpool()->create();
    Sanctum::actingAs($user, ['spools:read']);

    $this->getJson('/api/v1/catalog?owned=boxed')
        ->assertOk()
        ->assertJsonPath('data.0.boxed_count', 2)
        ->assertJsonPath('data.0.on_spool_count', 1)
        ->assertJsonPath('data.0.owned_count', 3)
        ->assertJsonPath('data.0.buy_url', 'https://eu.store.bambulab.com/search?q=10100');

    $this->getJson('/api/v1/catalog?owned=low')->assertStatus(422);
});

it('adds, opens, uses up, updates and removes a spool', function () {
    Sanctum::actingAs(user(), ['spools:read', 'spools:write']);
    $product = FilamentProduct::factory()->create();

    $uuid = $this->postJson('/api/v1/spools', ['filament_product_id' => $product->id, 'location' => 'AMS 1'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'boxed')
        ->assertJsonMissingPath('data.remaining_weight')
        ->json('data.uuid');

    $this->postJson("/api/v1/spools/{$uuid}/open")->assertOk()->assertJsonPath('data.status', 'on_spool');
    $this->postJson("/api/v1/spools/{$uuid}/open")->assertStatus(422);

    $this->patchJson("/api/v1/spools/{$uuid}", ['location' => 'Drybox'])->assertJsonPath('data.location', 'Drybox');

    $this->postJson("/api/v1/spools/{$uuid}/used-up", ['note' => 'last print'])->assertOk()->assertJsonPath('data.status', 'used_up');

    $this->getJson("/api/v1/spools/{$uuid}/events")
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.type', 'used_up')
        ->assertJsonPath('data.0.note', 'last print');

    $this->deleteJson("/api/v1/spools/{$uuid}")->assertNoContent();
    $this->getJson("/api/v1/spools/{$uuid}")->assertNotFound();
    expect(Spool::withTrashed()->where('uuid', $uuid)->exists())->toBeTrue();

    $this->getJson('/api/v1/spools?updated_since='.urlencode(now()->subHour()->toIso8601String()))
        ->assertJsonPath('data.0.uuid', $uuid)
        ->assertJsonPath('data.0.deleted_at', fn ($v) => $v !== null);
});

it('never shows another user\'s spool', function (string $method, string $suffix) {
    $other = Spool::factory()->create();
    Sanctum::actingAs(user(), ['spools:read', 'spools:write']);

    $this->json($method, "/api/v1/spools/{$other->uuid}{$suffix}", ['location' => 'x'])->assertNotFound();
    expect($other->fresh())->status->toBe(SpoolStatus::Boxed)->location->toBe('Shelf')->trashed()->toBeFalse();
})->with([
    ['GET', ''], ['PATCH', ''], ['DELETE', ''], ['POST', '/open'], ['POST', '/used-up'], ['GET', '/events'],
]);

it('lists only own spools', function () {
    $user = user();
    Spool::factory()->for($user)->create();
    Spool::factory()->create();
    Sanctum::actingAs($user, ['spools:read']);

    $this->getJson('/api/v1/spools')->assertJsonCount(1, 'data');
});

it('refuses writes with a read-only token', function () {
    $spool = Spool::factory()->for($user = user())->create();
    Sanctum::actingAs($user, ['spools:read']);

    $this->postJson("/api/v1/spools/{$spool->uuid}/open")->assertForbidden();
    $this->postJson('/api/v1/spools', ['filament_product_id' => $spool->filament_product_id])->assertForbidden();
    expect($spool->fresh()->status)->toBe(SpoolStatus::Boxed);
});

it('validates input', function () {
    Sanctum::actingAs(user(), ['spools:read', 'spools:write']);

    $this->postJson('/api/v1/spools', ['filament_product_id' => 999])->assertStatus(422)->assertJsonValidationErrors('filament_product_id');
});
