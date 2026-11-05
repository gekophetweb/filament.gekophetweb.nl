<?php

namespace App\Console\Commands;

use App\Actions\ImportCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('catalog:import {source : CSV/JSON file path, or an https URL} {--format=csv : csv, bambustudio or spoolmandb}')]
#[Description('Import (or re-import) filament products; idempotent on article_number')]
class ImportCatalogCommand extends Command
{
    public function handle(ImportCatalog $import): int
    {
        $source = (string) $this->argument('source');

        if (str_starts_with($source, 'https://')) {
            $contents = Http::timeout(30)->get($source)->throw()->body();
        } elseif (is_file($source)) {
            $contents = (string) file_get_contents($source);
        } else {
            $this->error("Source not found: {$source}");

            return self::FAILURE;
        }

        $rows = match ($this->option('format')) {
            'csv' => $import->readCsv($contents),
            'bambustudio' => $import->fromBambuStudio(json_decode($contents, true, flags: JSON_THROW_ON_ERROR)),
            'spoolmandb' => $import->fromSpoolmanDb(json_decode($contents, true, flags: JSON_THROW_ON_ERROR)),
            default => null,
        };

        if ($rows === null) {
            $this->error('Unknown --format; use csv, bambustudio or spoolmandb.');

            return self::FAILURE;
        }

        $stats = $import->rows($rows);

        $this->info("Created {$stats['created']}, updated {$stats['updated']}, unchanged {$stats['unchanged']}, skipped ".count($stats['skipped']).'.');
        foreach ($stats['skipped'] as $reason) {
            $this->warn("  skipped {$reason}");
        }

        return self::SUCCESS;
    }
}
