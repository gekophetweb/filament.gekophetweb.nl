<?php

use App\Livewire\Catalog;
use App\Livewire\Devices;
use App\Livewire\SpoolDetail;
use App\Livewire\Spools;
use App\Models\FilamentProduct;
use App\Models\Spool;
use Livewire\Livewire;

it('renders the catalog grid with swatches and filters by color', function () {
    $user = user();
    FilamentProduct::factory()->color('#0A2989')->create(['article_name' => 'Cobalt Blue']);
    FilamentProduct::factory()->color('#C12E1F')->create(['article_name' => 'Scarlet Red', 'color_hex_2' => '#FFFFFF']);

    $this->actingAs($user)->get('/')->assertOk()->assertSee('Cobalt Blue')->assertSee('linear-gradient', false);

    Livewire::actingAs($user)->test(Catalog::class)
        ->set('color', 'blue')
        ->assertSee('Cobalt Blue')
        ->assertDontSee('Scarlet Red');
});

it('adds a spool from the catalog', function () {
    $user = user();
    $product = FilamentProduct::factory()->create();

    Livewire::actingAs($user)->test(Catalog::class)->call('addSpool', $product->id)->assertSee('owned: 1');

    expect($user->spools()->count())->toBe(1);
});

it('weighs and empties own spools from the list', function () {
    $user = user();
    $spool = Spool::factory()->for($user)->create();

    Livewire::actingAs($user)->test(Spools::class)
        ->set("weights.{$spool->uuid}", 300)
        ->call('weigh', $spool->uuid)
        ->assertHasNoErrors();
    expect($spool->fresh()->remaining_weight)->toBe(300);

    Livewire::actingAs($user)->test(Spools::class)->call('markEmpty', $spool->uuid);
    expect($spool->fresh()->remaining_weight)->toBe(0);
});

it('cannot touch another user\'s spool through Livewire', function () {
    $other = Spool::factory()->create();

    Livewire::actingAs(user())->test(Spools::class)->call('markEmpty', $other->uuid)->assertNotFound();
    $this->actingAs(user())->get("/spools/{$other->uuid}")->assertNotFound();

    expect($other->fresh()->remaining_weight)->toBe(1000);
});

it('shows the detail page with history', function () {
    $user = user();
    $spool = Spool::factory()->for($user)->create();

    $this->actingAs($user)->get("/spools/{$spool->uuid}")->assertOk()->assertSee($spool->uuid);

    Livewire::actingAs($user)->test(SpoolDetail::class, ['spool' => $spool])
        ->set('location', 'Drybox')
        ->call('save');
    expect($spool->fresh()->location)->toBe('Drybox');
});

it('creates read-only and write tokens and revokes them', function () {
    $user = user();

    Livewire::actingAs($user)->test(Devices::class)
        ->set('name', 'Pixel')
        ->set('access', 'read')
        ->call('create')
        ->assertSet('plainToken', fn ($t) => is_string($t) && str_contains($t, '|'));

    $token = $user->tokens()->sole();
    expect($token->abilities)->toBe(['spools:read']);

    Livewire::actingAs($user)->test(Devices::class)->call('revoke', $token->id);
    expect($user->tokens()->count())->toBe(0);
});
