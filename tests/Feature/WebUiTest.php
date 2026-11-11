<?php

use App\Actions\AddSpool;
use App\Actions\AdjustInventory;
use App\Actions\MarkSpoolUsedUp;
use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Livewire\Catalog;
use App\Livewire\Devices;
use App\Livewire\SpoolDetail;
use App\Livewire\Spools;
use App\Models\FilamentProduct;
use App\Models\Material;
use App\Models\Spool;
use Livewire\Livewire;

it('renders the catalog as a list with swatches and filters by color', function () {
    $user = user();
    FilamentProduct::factory()->color('#0A2989')->create(['article_name' => 'Cobalt Blue']);
    FilamentProduct::factory()->color('#C12E1F')->create(['article_name' => 'Scarlet Red', 'color_hex_2' => '#FFFFFF']);

    $this->actingAs($user)->get('/')->assertOk()->assertSee('Cobalt Blue')->assertSee('linear-gradient', false);

    Livewire::actingAs($user)->test(Catalog::class)
        ->set('color', 'blue')
        ->assertSee('Cobalt Blue')
        ->assertDontSee('Scarlet Red');
});

it('switches the catalog between list and blocks and remembers the choice', function () {
    $user = user();
    FilamentProduct::factory()->create(['article_name' => 'Cobalt Blue']);

    Livewire::actingAs($user)->test(Catalog::class)
        ->assertSee('<table', false)
        ->set('display', 'blocks')
        ->assertDontSee('<table', false)
        ->assertSee('<article', false)
        ->assertSee('Cobalt Blue');

    Livewire::actingAs($user)->test(Catalog::class)->assertSet('display', 'blocks')->assertSee('<article', false);

    Livewire::actingAs($user)->test(Catalog::class)->set('display', 'bogus')->assertSee('<table', false);
});

it('changes boxed and on-spool counts from the catalog', function () {
    $user = user();
    $product = FilamentProduct::factory()->create(['article_number' => '10100']);

    Livewire::actingAs($user)->test(Catalog::class)
        ->call('addBoxed', $product->id)
        ->call('addBoxed', $product->id)
        ->call('putOnSpool', $product->id)
        ->assertSeeInOrder(['1', 'boxed', '1', 'on spool'])
        ->assertSee('https://eu.store.bambulab.com/search?q=10100', false);

    expect($user->spools()->where('status', SpoolStatus::Boxed)->count())->toBe(1)
        ->and($user->spools()->where('status', SpoolStatus::OnSpool)->count())->toBe(1);
});

it('filters own spools by filament type, counts boxed and on spool, and orders by article number', function () {
    $user = user();
    $asa = Material::firstOrCreate(['name' => 'ASA']);
    $white = FilamentProduct::factory()->create(['article_number' => '10100', 'article_name' => 'Jade White']);
    $black = FilamentProduct::factory()->create(['article_number' => '10101', 'article_name' => 'Black']);
    $grey = FilamentProduct::factory()->create(['article_number' => '45100', 'article_name' => 'ASA Grey', 'material_id' => $asa->id]);
    Spool::factory()->for($user)->create(['filament_product_id' => $black->id]);
    Spool::factory()->for($user)->onSpool()->create(['filament_product_id' => $white->id]);
    Spool::factory()->for($user)->create(['filament_product_id' => $white->id]);
    Spool::factory()->for($user)->onSpool()->create(['filament_product_id' => $grey->id]);
    Spool::factory()->for($user)->usedUp()->create(['filament_product_id' => $white->id]);
    Spool::factory()->onSpool()->create(['filament_product_id' => $grey->id]);

    Livewire::actingAs($user)->test(Spools::class)
        ->assertViewHas('boxedCount', 2)
        ->assertViewHas('onSpoolCount', 2)
        ->assertSeeInOrder(['Jade White', 'Jade White', 'Black', 'ASA Grey'])
        ->assertDontSee('Location')
        ->set('material', 'ASA')
        ->assertViewHas('boxedCount', 0)
        ->assertViewHas('onSpoolCount', 1)
        ->assertSee('ASA Grey')
        ->assertDontSee('Jade White');
});

it('keeps only the export button on My spools and moves sharing and import to their own page', function () {
    $user = user();

    $this->actingAs($user)->get('/spools')->assertOk()
        ->assertSee(route('spools.export'), false)
        ->assertDontSee('Print request link')
        ->assertDontSee('Import spools from CSV');

    $this->actingAs($user)->get('/share-export')->assertOk()
        ->assertSee('Print request link')
        ->assertSee(route('spools.export'), false)
        ->assertSee('Import spools from CSV');
});

it('uses dark mode by default and light mode once the browser stores that choice', function () {
    $user = user();

    $this->actingAs($user)->get('/spools')->assertOk()
        ->assertSee('<html lang="en" class="h-full dark">', false)
        ->assertSee('Switch to light mode');

    $this->actingAs($user)->withUnencryptedCookie('theme', 'light')->get('/spools')->assertOk()
        ->assertSee('<html lang="en" class="h-full">', false)
        ->assertSee('Switch to dark mode');

    $this->withUnencryptedCookie('theme', 'light')->get('/docs/api')->assertOk()
        ->assertSee('<html lang="en" class="h-full">', false);
    $this->withUnencryptedCookie('theme', 'bogus')->get('/docs/api')->assertOk()
        ->assertSee('<html lang="en" class="h-full dark">', false);
});

it('never lists used-up spools on My spools, whatever the status filter', function () {
    $user = user();
    $usedUp = Spool::factory()->for($user)->usedUp()->create();
    Spool::factory()->for($user)->create();

    foreach (['active', 'used_up', 'archived', 'all'] as $status) {
        Livewire::actingAs($user)->withQueryParams(['status' => $status])->test(Spools::class)
            ->assertSee('Boxed')
            ->assertDontSee($usedUp->uuid);
    }
});

it('puts own spools on the holder and marks them used up from the list', function () {
    $user = user();
    $spool = Spool::factory()->for($user)->create();

    Livewire::actingAs($user)->test(Spools::class)->call('putOnSpool', $spool->uuid);
    expect($spool->fresh()->status)->toBe(SpoolStatus::OnSpool);

    Livewire::actingAs($user)->test(Spools::class)->call('useUp', $spool->uuid);
    expect($spool->fresh()->status)->toBe(SpoolStatus::UsedUp);
});

it('cannot touch another user\'s spool through Livewire', function () {
    $other = Spool::factory()->create();

    Livewire::actingAs(user())->test(Spools::class)->call('useUp', $other->uuid)->assertNotFound();
    $this->actingAs(user())->get("/spools/{$other->uuid}")->assertNotFound();

    expect($other->fresh()->status)->toBe(SpoolStatus::Boxed);
});

it('shows a filament page with own history, a usage chart and a buy link', function () {
    $user = user();
    $product = FilamentProduct::factory()->create(['article_name' => 'Jade White', 'article_number' => '10100']);
    $spool = app(AddSpool::class)->handle($user, ['filament_product_id' => $product->id]);
    app(MarkSpoolUsedUp::class)->handle($spool, 'mine');
    $theirs = app(AddSpool::class)->handle(user(), ['filament_product_id' => $product->id]);
    app(MarkSpoolUsedUp::class)->handle($theirs, 'not mine');

    $this->actingAs($user)->get("/filaments/{$product->id}")
        ->assertOk()
        ->assertSee('Jade White')
        ->assertSee('https://eu.store.bambulab.com/search?q=10100', false)
        ->assertSee(now()->format('F Y').': 1 added, 1 used up')
        ->assertSeeInOrder(['Used up', 'mine', 'Added (boxed)'])
        ->assertDontSee('not mine');
});

it('shows overall usage with the most used filaments', function () {
    $user = user();
    $product = FilamentProduct::factory()->create(['article_name' => 'Jade White']);
    $inventory = app(AdjustInventory::class);
    $inventory->add($user, $product);
    $inventory->add($user, $product);
    $inventory->open($user, $product);
    $inventory->useUp($user, $product);

    $this->actingAs($user)->get('/usage')
        ->assertOk()
        ->assertSee(now()->format('F Y').': 2 added, 1 used up')
        ->assertSeeInOrder(['Most used filaments', 'Jade White', '1', 'used up']);

    $this->actingAs(user())->get('/usage')->assertOk()->assertDontSee('Jade White');
});

it('shows the detail page with history', function () {
    $user = user();
    $spool = Spool::factory()->for($user)->create();
    $spool->events()->create(['type' => SpoolEventType::Added, 'note' => 'From the shop']);

    $this->actingAs($user)->get("/spools/{$spool->uuid}")->assertOk()
        ->assertSee($spool->uuid)
        ->assertSee('From the shop')
        ->assertDontSee('Location')
        ->assertDontSee('Notes');

    Livewire::actingAs(user())->test(SpoolDetail::class, ['spool' => $spool])->assertNotFound();
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

it('limits how many active tokens a user can have', function () {
    config(['access.max_tokens_per_user' => 2]);
    $user = user();
    $user->createToken('old', ['spools:read'], now()->subDay());
    $user->createToken('one', ['spools:read']);

    Livewire::actingAs($user)->test(Devices::class)
        ->set('name', 'two')
        ->call('create')
        ->assertHasNoErrors()
        ->set('name', 'three')
        ->call('create')
        ->assertHasErrors(['name' => 'You can have at most 2 active tokens. Revoke one you no longer use first.']);

    expect($user->tokens()->count())->toBe(3);
});
