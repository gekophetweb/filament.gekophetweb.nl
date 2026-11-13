<?php

use App\Livewire\PrintRequests;
use App\Models\FilamentProduct;
use App\Models\Material;
use App\Models\Spool;
use App\Notifications\PrintRequestReceived;
use App\Support\FilamentEstimate;
use App\Support\StlMeasurement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The 12 triangles of a cube from the origin with sides of $size mm, wound outwards.
 *
 * @return list<list<array{0: float, 1: float, 2: float}>>
 */
function cubeTriangles(float $size): array
{
    $s = $size;
    $quads = [
        [[0, 0, 0], [0, $s, 0], [$s, $s, 0], [$s, 0, 0]],
        [[0, 0, $s], [$s, 0, $s], [$s, $s, $s], [0, $s, $s]],
        [[0, 0, 0], [$s, 0, 0], [$s, 0, $s], [0, 0, $s]],
        [[0, $s, 0], [0, $s, $s], [$s, $s, $s], [$s, $s, 0]],
        [[0, 0, 0], [0, 0, $s], [0, $s, $s], [0, $s, 0]],
        [[$s, 0, 0], [$s, $s, 0], [$s, $s, $s], [$s, 0, $s]],
    ];

    return collect($quads)->flatMap(fn (array $q) => [[$q[0], $q[1], $q[2]], [$q[0], $q[2], $q[3]]])->all();
}

function binaryCubeStl(float $size): string
{
    $triangles = cubeTriangles($size);
    $stl = str_pad('binary cube', 80, ' ').pack('V', count($triangles));

    foreach ($triangles as $triangle) {
        $stl .= pack('g3', 0, 0, 0).pack('g9', ...array_merge(...$triangle)).pack('v', 0);
    }

    return $stl;
}

function asciiCubeStl(float $size): string
{
    $facets = collect(cubeTriangles($size))->map(fn (array $t) => "  facet normal 0 0 0\n    outer loop\n"
        .collect($t)->map(fn (array $v) => '      vertex '.implode(' ', $v)."\n")->join('')
        ."    endloop\n  endfacet\n")->join('');

    return "solid cube\n{$facets}endsolid cube\n";
}

function stlFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'stl');
    file_put_contents($path, $contents);

    return $path;
}

it('measures the volume and size of a binary and an ASCII STL', function (string $contents) {
    $measurement = StlMeasurement::fromFile(stlFile($contents));

    expect($measurement->volume)->toEqualWithDelta(8000.0, 0.01)
        ->and($measurement->dimensions)->toBe([20.0, 20.0, 20.0])
        ->and($measurement->triangles)->toBe(12);
})->with([
    'binary' => [binaryCubeStl(20)],
    'ASCII' => [asciiCubeStl(20)],
]);

it('gives no measurement for a file that is not an STL', function () {
    expect(StlMeasurement::fromFile(stlFile('<html>not a model</html>')))->toBeNull()
        ->and(StlMeasurement::fromFile(stlFile('')))->toBeNull();
});

it('estimates grams from the volume and the material density', function () {
    // A 20 mm cube: 8 cm³, 9.92 g of solid PLA, 30-50% of that printed.
    expect(FilamentEstimate::forVolume(8000, ['PLA'])->label())->toBe('≈ 3–5 g of PLA')
        ->and(FilamentEstimate::forVolume(8000, [])->label())->toBe('≈ 3–5 g of PLA')
        ->and(FilamentEstimate::forVolume(1_000_000, ['ABS'])->label())->toBe('≈ 310–520 g of ABS')
        ->and(FilamentEstimate::densityOf('PETG-CF'))->toBe(1.30)
        ->and(FilamentEstimate::densityOf('PETG HF'))->toBe(1.27)
        ->and(FilamentEstimate::densityOf('Wood'))->toBe(FilamentEstimate::DEFAULT_DENSITY);
});

it('prices the estimate at the price of a 1 kg spool', function () {
    config(['access.print_request_spool_price' => 18]);

    // 1000 cm³ of PLA: 1240 g solid, so 370–620 g printed.
    expect(FilamentEstimate::forVolume(1_000_000, ['PLA'])->priceLabel())->toBe('≈ €6.66–11.16')
        ->and(FilamentEstimate::forVolume(10, ['PLA'])->priceLabel())->toBe('≈ €0.02');
});

it('flags sizes that suggest the STL is not in millimetres', function () {
    expect(StlMeasurement::looksMisScaled([2.0, 1.5, 1.0]))->toBeTrue()
        ->and(StlMeasurement::looksMisScaled([1200.0, 10.0, 10.0]))->toBeTrue()
        ->and(StlMeasurement::looksMisScaled([40.0, 30.0, 18.0]))->toBeFalse();
});

it('estimates the filament when a request comes in and shows it to the owner', function () {
    Storage::fake('local');
    Notification::fake();
    $owner = user();
    $owner->rotateShareToken();
    $owner->forceFill(['print_requester_emails' => '*'])->save();
    $petg = FilamentProduct::factory()->create(['material_id' => Material::firstOrCreate(['name' => 'PETG'])->id]);
    Spool::factory()->for($owner)->create(['filament_product_id' => $petg->id]);

    $this->withSession(['print_requester' => ['email' => 'sam@example.com', 'name' => 'Sam']])
        ->post(route('share.print-requests.store', $owner->share_token), [
            'requester_name' => 'Sam',
            'stl' => UploadedFile::fake()->createWithContent('cube.stl', binaryCubeStl(40)),
            'colors' => [$petg->id],
        ])->assertSessionHasNoErrors();

    // 64 cm³ of PETG: 81.3 g solid.
    $printRequest = $owner->printRequests()->sole();
    expect($printRequest->stl_volume)->toEqualWithDelta(64000.0, 0.01)
        ->and($printRequest->dimensionsLabel())->toBe('40 × 40 × 40 mm')
        ->and($printRequest->filamentEstimate()->label())->toBe('≈ 24–41 g of PETG');

    $this->get($owner->shareUrl())->assertSee(['≈ 24–41 g of PETG', '≈ €0.43–0.74']);
    Livewire::actingAs($owner)->test(PrintRequests::class)->call('open', $printRequest->uuid)
        ->assertSee(['≈ 24–41 g of PETG', '≈ €0.43–0.74', '40 × 40 × 40 mm']);
    Notification::assertSentTo($owner, PrintRequestReceived::class, fn (PrintRequestReceived $n) => in_array(
        'Estimated filament: ≈ 24–41 g of PETG, ≈ €0.43–0.74 (40 × 40 × 40 mm, without supports)', $n->toMail($owner)->introLines, true,
    ));
});

it('keeps a request whose STL cannot be measured, without an estimate', function () {
    Storage::fake('local');
    $owner = user();
    $owner->rotateShareToken();
    $owner->forceFill(['print_requester_emails' => '*'])->save();

    $this->withSession(['print_requester' => ['email' => 'sam@example.com', 'name' => 'Sam']])
        ->post(route('share.print-requests.store', $owner->share_token), [
            'requester_name' => 'Sam',
            'stl' => UploadedFile::fake()->createWithContent('broken.stl', 'solid nothing here'),
        ])->assertSessionHasNoErrors();

    expect($owner->printRequests()->sole())->stl_volume->toBeNull()->filamentEstimate()->toBeNull();
});
