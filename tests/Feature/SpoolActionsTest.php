<?php

use App\Actions\AddSpool;
use App\Actions\AdjustInventory;
use App\Actions\MarkSpoolUsedUp;
use App\Actions\OpenSpool;
use App\Actions\RemoveSpool;
use App\Actions\SearchCatalog;
use App\Actions\UpdateSpool;
use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Models\Brand;
use App\Models\FilamentProduct;
use App\Models\Spool;
use Illuminate\Validation\ValidationException;

it('adds a boxed spool with an added event', function () {
    $user = user();
    $product = FilamentProduct::factory()->create();

    $spool = app(AddSpool::class)->handle($user, ['filament_product_id' => $product->id, 'notes' => 'gift']);

    expect($spool->user_id)->toBe($user->id)
        ->and($spool->uuid)->toBeString()
        ->and($spool->status)->toBe(SpoolStatus::Boxed)
        ->and($spool->events()->sole()->type)->toBe(SpoolEventType::Added);
});

it('rejects unknown products and adding a spool that is already used up', function (array $input) {
    app(AddSpool::class)->handle(user(), $input);
})->with([
    'unknown product' => [['filament_product_id' => 999]],
    'used up' => [fn () => ['filament_product_id' => FilamentProduct::factory()->create()->id, 'status' => 'used_up']],
])->throws(ValidationException::class);

it('puts a boxed spool on the spool holder and then marks it used up', function () {
    $spool = Spool::factory()->create();

    app(OpenSpool::class)->handle($spool);
    expect($spool->fresh()->status)->toBe(SpoolStatus::OnSpool)
        ->and($spool->events()->first()->type)->toBe(SpoolEventType::Opened);

    app(MarkSpoolUsedUp::class)->handle($spool);
    expect($spool->fresh()->status)->toBe(SpoolStatus::UsedUp)
        ->and($spool->events()->first()->type)->toBe(SpoolEventType::UsedUp);
});

it('only opens boxed spools and only uses up spools that hold filament', function () {
    expect(fn () => app(OpenSpool::class)->handle(Spool::factory()->onSpool()->create()))->toThrow(ValidationException::class)
        ->and(fn () => app(MarkSpoolUsedUp::class)->handle(Spool::factory()->usedUp()->create()))->toThrow(ValidationException::class);
});

it('removes a spool with a removed event, keeping it in the database', function () {
    $spool = Spool::factory()->create();

    app(RemoveSpool::class)->handle($spool, 'added by mistake');

    expect($spool->fresh()->trashed())->toBeTrue()
        ->and($spool->events()->first())->type->toBe(SpoolEventType::Removed)->note->toBe('added by mistake');
});

it('adjusts boxed and on-spool counts per product, oldest spool first', function () {
    $user = user();
    $product = FilamentProduct::factory()->create();
    $inventory = app(AdjustInventory::class);

    $first = $inventory->add($user, $product);
    $inventory->add($user, $product);
    $inventory->add($user, $product);

    expect($inventory->open($user, $product)->id)->toBe($first->id)
        ->and($inventory->useUp($user, $product)->id)->toBe($first->id)
        ->and($inventory->remove($user, $product))->toBeTrue();

    $counts = app(SearchCatalog::class)->query($user)->get()->firstWhere('id', $product->id);
    expect($counts->boxed_count)->toBe(1)->and($counts->on_spool_count)->toBe(0)->and($counts->owned_count)->toBe(1);
});

it('does nothing when there is no spool to open, use up or remove', function () {
    $user = user();
    $product = FilamentProduct::factory()->create();
    Spool::factory()->for($product, 'product')->create(); // someone else's boxed spool
    $inventory = app(AdjustInventory::class);

    expect($inventory->open($user, $product))->toBeNull()
        ->and($inventory->useUp($user, $product))->toBeNull()
        ->and($inventory->remove($user, $product))->toBeFalse()
        ->and(Spool::where('status', SpoolStatus::Boxed)->count())->toBe(1);
});

it('records a moved event when the location changes', function () {
    $spool = Spool::factory()->create(['location' => 'Shelf']);

    app(UpdateSpool::class)->handle($spool, ['location' => 'AMS 1 slot 2']);

    expect($spool->events()->first())->type->toBe(SpoolEventType::Moved)->note->toBe('AMS 1 slot 2');
});

it('filters the catalog by text, color family and ownership', function () {
    $user = user();
    $red = FilamentProduct::factory()->color('#C12E1F')->create(['article_name' => 'Scarlet Red']);
    $blue = FilamentProduct::factory()->color('#0A2989')->create(['article_name' => 'Cobalt Blue']);
    $green = FilamentProduct::factory()->color('#00AE42')->create(['article_name' => 'Bambu Green']);
    Spool::factory()->for($user)->for($red, 'product')->onSpool()->create();
    Spool::factory()->for($user)->for($green, 'product')->create();
    Spool::factory()->for($user)->for($green, 'product')->onSpool()->create();
    Spool::factory()->for(user())->for($blue, 'product')->create(); // someone else's

    $names = fn (array $filters) => app(SearchCatalog::class)->query($user, $filters)->pluck('article_name')->sort()->values()->all();

    expect($names(['q' => 'scar']))->toBe(['Scarlet Red'])
        ->and($names(['color' => 'blue']))->toBe(['Cobalt Blue'])
        ->and($names(['owned' => SearchCatalog::OWNED]))->toBe(['Bambu Green', 'Scarlet Red'])
        ->and($names(['owned' => SearchCatalog::NOT_OWNED]))->toBe(['Cobalt Blue'])
        ->and($names(['owned' => SearchCatalog::BOXED]))->toBe(['Bambu Green'])
        ->and($names(['owned' => SearchCatalog::ON_SPOOL]))->toBe(['Bambu Green', 'Scarlet Red'])
        ->and($names(['owned' => SearchCatalog::RUNNING_OUT]))->toBe(['Scarlet Red']);
});

it('links Bambu Lab filaments to the store search by article number', function () {
    config(['services.bambulab.store_url' => 'https://eu.store.bambulab.com']);
    $bambu = Brand::firstOrCreate(['name' => 'Bambu Lab']);

    $coded = FilamentProduct::factory()->for($bambu)->create(['article_number' => '10100']);
    $uncoded = FilamentProduct::factory()->for($bambu)->create(['article_number' => 'SDB-x', 'line' => 'Basic', 'article_name' => 'Jade White']);
    $withUrl = FilamentProduct::factory()->for($bambu)->create(['product_url' => 'https://example.test/jade']);
    $otherBrand = FilamentProduct::factory()->for(Brand::firstOrCreate(['name' => 'Other']))->create();

    expect($coded->buyUrl())->toBe('https://eu.store.bambulab.com/search?q=10100')
        ->and($uncoded->buyUrl())->toStartWith('https://eu.store.bambulab.com/search?q=')->toContain('Basic+Jade+White')
        ->and($withUrl->buyUrl())->toBe('https://example.test/jade')
        ->and($otherBrand->buyUrl())->toBeNull();
});
