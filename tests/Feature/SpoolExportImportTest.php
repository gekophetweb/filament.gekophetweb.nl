<?php

use App\Actions\ImportSpools;
use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Livewire\ShareAndExport;
use App\Models\FilamentProduct;
use App\Models\Spool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;

function csvFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'spools');
    file_put_contents($path, $contents);

    return $path;
}

it('exports only the user\'s own, not deleted spools as CSV', function () {
    $user = user();
    $product = FilamentProduct::factory()->create(['article_number' => '10100', 'article_name' => '=HYPERLINK("x")']);
    $mine = Spool::factory()->for($user)->onSpool()->create([
        'filament_product_id' => $product->id, 'is_refill' => true, 'purchased_at' => '2026-03-01',
    ]);
    Spool::factory()->for($user)->create(['filament_product_id' => $product->id])->delete();
    $theirs = Spool::factory()->create(['filament_product_id' => $product->id]);

    $response = $this->actingAs($user)->get('/spools/export')->assertOk()->assertDownload('spools-'.now()->toDateString().'.csv');
    $csv = $response->streamedContent();
    $rows = array_map(fn ($line) => str_getcsv($line, escape: ''), explode("\n", trim(substr($csv, 3))));

    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['id', 'article_number', 'filament', 'material', 'status', 'refill', 'purchased_at', 'added_at'])
        ->and($rows[1][0])->toBe($mine->uuid)
        ->and($rows[1][1])->toBe('10100')
        ->and($rows[1][2])->toBe("'=HYPERLINK(\"x\")")
        ->and($rows[1][4])->toBe('on_spool')
        ->and($rows[1][5])->toBe('yes')
        ->and($rows[1][6])->toBe('2026-03-01')
        ->and($csv)->not->toContain($theirs->uuid);
});

it('requires sign-in to export', function () {
    $this->get('/spools/export')->assertRedirect('/login');
});

it('round-trips an export: unchanged for the owner, new spools for someone else', function () {
    $owner = user();
    $product = FilamentProduct::factory()->create();
    $spool = Spool::factory()->for($owner)->create([
        'filament_product_id' => $product->id, 'is_refill' => true, 'purchased_at' => '2026-03-01',
    ]);
    $csv = $this->actingAs($owner)->get('/spools/export')->streamedContent();

    expect(app(ImportSpools::class)->handle($owner, csvFile($csv)))
        ->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 1, 'errors' => []]);

    $other = user();
    expect(app(ImportSpools::class)->handle($other, csvFile($csv))['created'])->toBe(1);

    $copy = $other->spools()->sole();
    expect($copy->uuid)->not->toBe($spool->uuid)
        ->and($copy->is_refill)->toBeTrue()
        ->and($copy->purchased_at->toDateString())->toBe('2026-03-01')
        ->and($spool->fresh()->user_id)->toBe($owner->id);
});

it('updates own spools by id, adds the rest, ignores old price/location/notes columns and reports skipped rows', function () {
    $user = user();
    FilamentProduct::factory()->create(['article_number' => '10100']);
    $other = FilamentProduct::factory()->create(['article_number' => '10200']);
    $spool = Spool::factory()->for($user)->create(['filament_product_id' => $other->id]);
    $newId = (string) Str::uuid();

    $result = app(ImportSpools::class)->handle($user, csvFile(implode("\n", [
        'id;Article number;Status;Refill;Price;Location;Notes;Added at',
        "{$spool->uuid};10200;On spool;nee;€ 12,50;Printer;;",
        "{$newId};10100;Used up;ja;;;\"two\nlines\";2025-01-15",
        ';99999;boxed;;;;;',
        ';10100;lost;;;;;',
    ])));

    expect($result)->toBe(['created' => 1, 'updated' => 1, 'unchanged' => 0, 'errors' => [
        'Row 4: unknown article number "99999".',
        'Row 5: The selected status is invalid.',
    ]]);

    $spool->refresh();
    expect($spool->status)->toBe(SpoolStatus::OnSpool)
        ->and($spool->events()->first()->type)->toBe(SpoolEventType::Opened);

    $added = Spool::where('uuid', $newId)->sole();
    expect($added->user_id)->toBe($user->id)
        ->and($added->status)->toBe(SpoolStatus::UsedUp)
        ->and($added->is_refill)->toBeTrue()
        ->and($added->events()->sole()->created_at->toDateString())->toBe('2025-01-15');
});

it('reads a CSV that Excel saved in the Windows code page', function () {
    $user = user();
    FilamentProduct::factory()->create(['article_number' => '10100']);

    $result = app(ImportSpools::class)->handle($user, csvFile(mb_convert_encoding("article_number,filament\n10100,Jade\ncafé,Kast\n", 'Windows-1252', 'UTF-8')));

    expect($result['created'])->toBe(1)
        ->and($result['errors'])->toBe(['Row 3: unknown article number "café".']);
});

it('rejects a file without an article_number column', function () {
    app(ImportSpools::class)->handle(user(), csvFile("name,status\nJade White,boxed"));
})->throws(InvalidArgumentException::class, 'article_number');

it('imports an uploaded CSV from the Share & export page and shows the result', function () {
    $user = user();
    FilamentProduct::factory()->create(['article_number' => '10100', 'article_name' => 'Jade White']);

    Livewire::actingAs($user)->test(ShareAndExport::class)
        ->set('importFile', UploadedFile::fake()->createWithContent('spools.csv', "article_number\n10100\n10100\n"))
        ->call('import')
        ->assertHasNoErrors()
        ->assertSee('Import done: 2 added, 0 updated, 0 unchanged.');

    Livewire::actingAs($user)->test(ShareAndExport::class)
        ->set('importFile', UploadedFile::fake()->createWithContent('spools.csv', "name\nx\n"))
        ->call('import')
        ->assertHasErrors('importFile');

    expect($user->spools()->count())->toBe(2);
});
