<?php

namespace App\Actions;

use App\Models\Brand;
use App\Models\FilamentProduct;
use App\Models\Material;
use App\Support\Color;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Idempotent catalog import keyed on article_number (SKU).
 * Re-running updates existing products and adds new colors.
 */
class ImportCatalog
{
    public const CSV_COLUMNS = [
        'brand', 'material', 'line', 'article_name', 'article_number', 'color_hex',
        'color_hex_2', 'diameter', 'nominal_weight', 'has_refill', 'product_url',
    ];

    /**
     * @param  iterable<array<string, mixed>>  $rows  rows keyed by CSV_COLUMNS
     * @return array{created: int, updated: int, unchanged: int, skipped: list<string>}
     */
    public function rows(iterable $rows): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => []];
        $brands = [];
        $materials = [];

        DB::transaction(function () use ($rows, &$stats, &$brands, &$materials) {
            foreach ($rows as $i => $row) {
                $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
                $sku = (string) ($row['article_number'] ?? '');
                $hex = Color::normalize($row['color_hex'] ?? null);

                if ($sku === '' || $hex === null || empty($row['brand']) || empty($row['material']) || empty($row['article_name'])) {
                    $stats['skipped'][] = 'row '.($i + 1).': missing article_number, brand, material, article_name or a valid color_hex';

                    continue;
                }

                $brandId = $brands[$row['brand']] ??= Brand::firstOrCreate(['name' => $row['brand']])->id;
                $materialId = $materials[$row['material']] ??= Material::firstOrCreate(['name' => $row['material']])->id;

                $product = FilamentProduct::firstOrNew(['article_number' => $sku]);
                $product->fill([
                    'brand_id' => $brandId,
                    'material_id' => $materialId,
                    'line' => ($row['line'] ?? '') !== '' ? $row['line'] : null,
                    'article_name' => $row['article_name'],
                    'color_hex' => $hex,
                    'color_hex_2' => Color::normalize($row['color_hex_2'] ?? null),
                    'color_family' => Color::family($hex),
                    'diameter' => (float) (($row['diameter'] ?? '') !== '' ? $row['diameter'] : 1.75),
                    'nominal_weight' => (int) (($row['nominal_weight'] ?? '') !== '' ? $row['nominal_weight'] : 1000),
                    'has_refill' => filter_var($row['has_refill'] ?? false, FILTER_VALIDATE_BOOL),
                    'product_url' => ($row['product_url'] ?? '') !== '' ? $row['product_url'] : null,
                    'is_active' => true,
                ]);

                $key = ! $product->exists ? 'created' : ($product->isDirty() ? 'updated' : 'unchanged');
                $product->save();
                $stats[$key]++;
            }
        });

        return $stats;
    }

    /** @return \Generator<int, array<string, string>> */
    public function readCsv(string $contents): \Generator
    {
        $lines = preg_split('/\r\n|\n|\r/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $contents)));
        $header = array_map(fn ($h) => Str::snake(trim($h)), str_getcsv(array_shift($lines), escape: ''));

        if ($missing = array_diff(['brand', 'material', 'article_name', 'article_number', 'color_hex'], $header)) {
            throw new InvalidArgumentException('CSV is missing columns: '.implode(', ', $missing));
        }

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line, escape: '');
            yield array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
        }
    }

    /**
     * Convert a SpoolmanDB manufacturer file (filaments/<brand>.json) into rows.
     * SpoolmanDB has no SKUs, so article_number becomes a stable "SDB-..." slug.
     *
     * @param  array<string, mixed>  $data
     * @return \Generator<int, array<string, mixed>>
     */
    public function fromSpoolmanDb(array $data): \Generator
    {
        $brand = (string) ($data['manufacturer'] ?? '');

        foreach ($data['filaments'] ?? [] as $filament) {
            $template = (string) ($filament['name'] ?? '{color_name}');
            $prefix = trim(Str::before($template, '{color_name}'));
            $line = $prefix !== '' ? $prefix : (($filament['material'] ?? '') === 'PLA' ? 'Basic' : null);

            foreach ($filament['weights'] ?? [['weight' => 1000]] as $weight) {
                foreach ($filament['diameters'] ?? [1.75] as $diameter) {
                    foreach ($filament['colors'] ?? [] as $color) {
                        $hexes = $color['hexes'] ?? [$color['hex'] ?? null];
                        $name = trim(str_replace('{color_name}', (string) $color['name'], $template));

                        yield [
                            'brand' => $brand,
                            'material' => $filament['material'] ?? 'Unknown',
                            'line' => $line,
                            'article_name' => $name,
                            'article_number' => 'SDB-'.Str::slug("{$brand} {$filament['material']} {$name} {$weight['weight']} {$diameter}"),
                            'color_hex' => $hexes[0] ?? null,
                            'color_hex_2' => $hexes[1] ?? null,
                            'diameter' => $diameter,
                            'nominal_weight' => $weight['weight'] ?? 1000,
                            'has_refill' => false,
                            'product_url' => null,
                        ];
                    }
                }
            }
        }
    }
}
