<?php

use App\Actions\ImportCatalog;
use App\Models\FilamentProduct;

beforeEach(function () {
    $this->csv = tempnam(sys_get_temp_dir(), 'cat').'.csv';
    file_put_contents($this->csv, implode("\n", [
        'brand,material,line,article_name,article_number,color_hex,color_hex_2,diameter,nominal_weight,has_refill,product_url',
        'Bambu Lab,PLA,Basic,Jade White,TEST-001,#FFFFFF,,1.75,1000,true,',
        'Bambu Lab,PLA,Silk,"Dual: Gold, Silver",TEST-002,F4A925,C0C0C0,1.75,1000,false,',
        'Bambu Lab,PLA,Basic,Broken,TEST-003,nothex,,1.75,1000,false,',
    ]));
});

it('imports a CSV and is idempotent on article_number', function () {
    $this->artisan('catalog:import', ['source' => $this->csv])
        ->expectsOutputToContain('Created 2, updated 0, unchanged 0, skipped 1')
        ->assertSuccessful();

    $dual = FilamentProduct::where('article_number', 'TEST-002')->sole();
    expect($dual->article_name)->toBe('Dual: Gold, Silver')
        ->and($dual->color_hex_2)->toBe('#C0C0C0')
        ->and($dual->color_family)->toBe('orange')
        ->and($dual->swatchCss())->toContain('linear-gradient');

    $this->artisan('catalog:import', ['source' => $this->csv])
        ->expectsOutputToContain('Created 0, updated 0, unchanged 2')
        ->assertSuccessful();

    expect(FilamentProduct::count())->toBe(2);
});

it('updates changed rows on re-import', function () {
    $this->artisan('catalog:import', ['source' => $this->csv])->assertSuccessful();
    file_put_contents($this->csv, str_replace('Jade White', 'Jade White v2', file_get_contents($this->csv)));

    $this->artisan('catalog:import', ['source' => $this->csv])
        ->expectsOutputToContain('Created 0, updated 1, unchanged 1')
        ->assertSuccessful();
});

it('converts SpoolmanDB manufacturer files', function () {
    $rows = iterator_to_array((new ImportCatalog)->fromSpoolmanDb([
        'manufacturer' => 'Bambu Lab',
        'filaments' => [
            ['name' => 'Matte {color_name}', 'material' => 'PLA', 'weights' => [['weight' => 1000]], 'diameters' => [1.75],
                'colors' => [['name' => 'Ivory White', 'hex' => 'FFFFFF']]],
            ['name' => '{color_name}', 'material' => 'PLA', 'weights' => [['weight' => 1000]], 'diameters' => [1.75],
                'colors' => [['name' => 'Arctic Whisper', 'hexes' => ['9CDBD9', 'FFFFFF']]]],
        ],
    ]), false);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray(['line' => 'Matte', 'article_name' => 'Matte Ivory White', 'color_hex' => 'FFFFFF'])
        ->and($rows[1])->toMatchArray(['line' => 'Basic', 'color_hex' => '9CDBD9', 'color_hex_2' => 'FFFFFF'])
        ->and($rows[0]['article_number'])->toStartWith('SDB-bambu-lab-pla-matte-ivory-white');

    expect((new ImportCatalog)->rows($rows)['created'])->toBe(2);
});

it('converts Bambu Studio color codes', function () {
    $rows = iterator_to_array((new ImportCatalog)->fromBambuStudio(['data' => [
        ['fila_color_code' => '10100', 'fila_type' => 'PLA Basic', 'fila_color_name' => ['en' => 'Jade White'], 'fila_color' => ['#FFFFFFFF']],
        ['fila_color_code' => '10900', 'fila_type' => 'PLA Basic', 'fila_color_name' => ['en' => 'Arctic Whisper'], 'fila_color' => ['#9CDBD9FF', '#FFFFFFFF']],
        ['fila_color_code' => '32101', 'fila_type' => 'PETG Translucent', 'fila_color_name' => ['en' => 'Clear'], 'fila_color' => ['#00000000']],
        ['fila_color_code' => '14100', 'fila_type' => 'PLA-CF', 'fila_color_name' => ['en' => 'Black'], 'fila_color' => ['#000000FF']],
        ['fila_color_code' => '65000', 'fila_type' => 'Support for PLA/PETG', 'fila_color_name' => ['en' => 'Nature'], 'fila_color' => ['#F0F0F0FF']],
        ['fila_color_code' => '53000', 'fila_type' => 'TPU 75A (2.85mm)', 'fila_color_name' => ['en' => 'White'], 'fila_color' => ['#FFFFFFFF']],
    ]]), false);

    expect($rows[0])->toMatchArray(['brand' => 'Bambu Lab', 'material' => 'PLA', 'line' => 'Basic', 'article_name' => 'Jade White', 'article_number' => '10100', 'color_hex' => '#FFFFFF', 'color_hex_2' => null])
        ->and($rows[1])->toMatchArray(['color_hex' => '#9CDBD9', 'color_hex_2' => '#FFFFFF'])
        ->and($rows[2])->toMatchArray(['material' => 'PETG', 'line' => 'Translucent', 'color_hex' => '#FFFFFF'])
        ->and($rows[3])->toMatchArray(['material' => 'PLA-CF', 'line' => null])
        ->and($rows[4])->toMatchArray(['material' => 'Support', 'line' => 'for PLA/PETG'])
        ->and($rows[5])->toMatchArray(['material' => 'TPU', 'line' => '75A', 'diameter' => 2.85]);
});

it('seeds the full Bambu Lab catalog idempotently', function () {
    $this->seed(Database\Seeders\BambuLabCatalogSeeder::class);

    $total = json_decode(file_get_contents(database_path('data/bambulab-filaments.json')), true)['total'];
    expect(FilamentProduct::count())->toBe($total)->toBeGreaterThan(300);

    $jade = FilamentProduct::with(['brand', 'material'])->where('article_number', '10100')->sole();
    expect($jade->article_name)->toBe('Jade White')
        ->and($jade->brand->name)->toBe('Bambu Lab')
        ->and($jade->material->name)->toBe('PLA')
        ->and($jade->line)->toBe('Basic')
        ->and($jade->color_hex)->toBe('#FFFFFF');

    $this->seed(Database\Seeders\BambuLabCatalogSeeder::class);
    expect(FilamentProduct::count())->toBe($total);
});

it('shows seeded filaments in the catalog', function () {
    $this->seed(Database\Seeders\BambuLabCatalogSeeder::class);

    $this->actingAs(user())->get('/?q=10100')
        ->assertOk()
        ->assertSee('Jade White')
        ->assertSee('10100')
        ->assertSee('#FFFFFF');
});
