<?php

namespace Database\Seeders;

use App\Actions\ImportCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds every Bambu Lab filament (name, article number, type, hex) from the
 * snapshot of Bambu Studio's filaments_color_codes.json in database/data.
 * Idempotent: re-running updates changed products and adds new colors.
 *
 * Refresh the snapshot straight from upstream with:
 *   php artisan catalog:import https://raw.githubusercontent.com/bambulab/BambuStudio/master/resources/profiles/BBL/filament/filaments_color_codes.json --format=bambustudio
 */
class BambuLabCatalogSeeder extends Seeder
{
    public const DATA_FILE = 'data/bambulab-filaments.json';

    public function run(ImportCatalog $import): void
    {
        $data = json_decode((string) file_get_contents(database_path(self::DATA_FILE)), true, flags: JSON_THROW_ON_ERROR);
        $stats = $import->rows($import->fromBambuStudio($data));

        $this->command?->info(sprintf(
            'Bambu Lab catalog: created %d, updated %d, unchanged %d, skipped %d.',
            $stats['created'], $stats['updated'], $stats['unchanged'], count($stats['skipped']),
        ));

        foreach ($stats['skipped'] as $reason) {
            $this->command?->warn("  skipped {$reason}");
        }
    }
}
