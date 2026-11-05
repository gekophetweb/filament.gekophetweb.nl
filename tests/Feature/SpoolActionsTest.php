<?php

use App\Actions\AddSpool;
use App\Actions\MarkSpoolEmpty;
use App\Actions\RecordWeight;
use App\Actions\SearchCatalog;
use App\Actions\UpdateSpool;
use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Models\FilamentProduct;
use App\Models\Spool;
use Illuminate\Validation\ValidationException;

it('adds a spool with the product weight and an added event', function () {
    $user = user();
    $product = FilamentProduct::factory()->create(['nominal_weight' => 750]);

    $spool = app(AddSpool::class)->handle($user, ['filament_product_id' => $product->id, 'notes' => 'gift']);

    expect($spool->user_id)->toBe($user->id)
        ->and($spool->uuid)->toBeString()
        ->and($spool->initial_weight)->toBe(750)
        ->and($spool->remaining_weight)->toBe(750)
        ->and($spool->status)->toBe(SpoolStatus::Sealed)
        ->and($spool->events()->sole()->type)->toBe(SpoolEventType::Added);
});

it('rejects unknown products', function () {
    app(AddSpool::class)->handle(user(), ['filament_product_id' => 999]);
})->throws(ValidationException::class);

it('records weight, moves sealed spools to in use and empties at zero', function () {
    $spool = Spool::factory()->create();

    app(RecordWeight::class)->handle($spool, 640);
    expect($spool->fresh()->status)->toBe(SpoolStatus::InUse)
        ->and($spool->fresh()->remaining_weight)->toBe(640);

    app(RecordWeight::class)->handle($spool, 0);
    expect($spool->fresh()->status)->toBe(SpoolStatus::Empty)
        ->and($spool->events()->first()->type)->toBe(SpoolEventType::Emptied);
});

it('rejects negative weights', function () {
    app(RecordWeight::class)->handle(Spool::factory()->create(), -5);
})->throws(ValidationException::class);

it('marks a spool empty', function () {
    $spool = app(MarkSpoolEmpty::class)->handle(Spool::factory()->remaining(300)->create());

    expect($spool->remaining_weight)->toBe(0)->and($spool->status)->toBe(SpoolStatus::Empty);
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
    Spool::factory()->for($user)->for($red, 'product')->remaining(100)->create();
    Spool::factory()->for(user())->for($blue, 'product')->create(); // someone else's

    $names = fn (array $f) => app(SearchCatalog::class)->query($user, $f)->pluck('article_name')->all();

    expect($names(['q' => 'scar']))->toBe(['Scarlet Red'])
        ->and($names(['color' => 'blue']))->toBe(['Cobalt Blue'])
        ->and($names(['owned' => '1']))->toBe(['Scarlet Red'])
        ->and($names(['owned' => '0']))->toBe(['Cobalt Blue'])
        ->and($names(['owned' => 'low']))->toBe(['Scarlet Red'])
        ->and(app(SearchCatalog::class)->query($user, [])->get()->firstWhere('id', $red->id)->owned_count)->toBe(1);
});
