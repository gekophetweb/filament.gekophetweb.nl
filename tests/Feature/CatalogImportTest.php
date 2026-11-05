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
