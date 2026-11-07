<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Models\FilamentProduct;
use App\Models\Spool;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Reads a spools CSV (as written by ExportSpools, or edited in a spreadsheet).
 *
 * A row whose id is one of the user's own spools updates it; any other row adds a spool.
 * Nothing is ever deleted. Rows that fail are skipped and reported with their line number.
 */
class ImportSpools
{
    public const MAX_ROWS = 5000;

    /**
     * @return array{created: int, updated: int, unchanged: int, errors: list<string>}
     */
    public function handle(User $user, string $path): array
    {
        $contents = (string) file_get_contents($path);
        if (! mb_check_encoding($contents, 'UTF-8')) {
            // Excel's plain "CSV" format saves in the Windows code page.
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        try {
            $first = (string) fgets($handle);
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            // Spreadsheets in many European locales save with semicolons.
            $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            $header = array_map(fn ($h) => Str::snake(trim((string) $h)), str_getcsv(trim($first), $delimiter, escape: ''));

            if (! in_array('article_number', $header, true)) {
                throw new InvalidArgumentException('The file needs at least an article_number column.');
            }

            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => []];
            $line = 1;

            DB::transaction(function () use ($user, $handle, $header, $delimiter, &$result, &$line) {
                while (($values = fgetcsv($handle, null, $delimiter, escape: '')) !== false) {
                    $line++;
                    if ($values === [null] || implode('', $values) === '') {
                        continue;
                    }
                    if ($line - 1 > self::MAX_ROWS) {
                        $result['errors'][] = 'Stopped after '.self::MAX_ROWS.' rows.';
                        break;
                    }

                    $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
                    try {
                        $result[$this->importRow($user, array_map(self::unsafeCell(...), $row))]++;
                    } catch (InvalidArgumentException $e) {
                        $result['errors'][] = "Row {$line}: {$e->getMessage()}";
                    }
                }
            });

            return $result;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(User $user, array $row): string
    {
        $product = FilamentProduct::where('article_number', trim($row['article_number'] ?? ''))->first();
        if (! $product) {
            throw new InvalidArgumentException('unknown article number "'.trim($row['article_number'] ?? '').'".');
        }

        $input = array_filter([
            'status' => self::status($row['status'] ?? ''),
            'is_refill' => self::boolean($row['refill'] ?? ''),
            'purchased_at' => trim($row['purchased_at'] ?? '') ?: null,
            'price' => self::price($row['price'] ?? ''),
            'location' => trim($row['location'] ?? '') ?: null,
            'notes' => trim($row['notes'] ?? '') ?: null,
            'added_at' => trim($row['added_at'] ?? '') ?: null,
        ], fn ($value) => $value !== null);

        $validator = Validator::make($input, [
            'status' => [Rule::enum(SpoolStatus::class)],
            'is_refill' => ['boolean'],
            'purchased_at' => ['date'],
            'price' => ['numeric', 'min:0', 'max:99999'],
            'location' => ['string', 'max:100'],
            'notes' => ['string', 'max:2000'],
            'added_at' => ['date'],
        ]);
        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }

        $id = trim($row['id'] ?? '');
        $spool = Str::isUuid($id) ? $user->spools()->where('uuid', $id)->first() : null;
        $data = collect($input)->except('added_at')->all() + ['filament_product_id' => $product->id];

        return $spool
            ? $this->update($spool, $data)
            : $this->create($user, $data, Str::isUuid($id) ? $id : null, $input['added_at'] ?? null);
    }

    /** @param array<string, mixed> $data */
    private function create(User $user, array $data, ?string $uuid, ?string $addedAt): string
    {
        $spool = new Spool($data + ['status' => SpoolStatus::Boxed]);
        $spool->user()->associate($user);
        // Keep the id from the file, unless some other spool (anyone's, even deleted) has it.
        $spool->uuid = $uuid && ! Spool::withTrashed()->where('uuid', $uuid)->exists() ? $uuid : null;
        if ($addedAt) {
            $spool->created_at = Carbon::parse($addedAt);
        }
        $spool->save();

        $event = $spool->events()->make(['type' => SpoolEventType::Added, 'note' => 'Imported']);
        $event->created_at = $spool->created_at;
        $event->save();

        return 'created';
    }

    /** @param array<string, mixed> $data */
    private function update(Spool $spool, array $data): string
    {
        $spool->fill($data);
        if (! $spool->isDirty()) {
            return 'unchanged';
        }

        $type = match (true) {
            ! $spool->isDirty('status') => $spool->isDirty('location') ? SpoolEventType::Moved : null,
            $spool->status === SpoolStatus::OnSpool => SpoolEventType::Opened,
            $spool->status === SpoolStatus::UsedUp => SpoolEventType::UsedUp,
            default => null,
        };
        $spool->save();

        if ($type) {
            $spool->events()->create(['type' => $type, 'note' => $type === SpoolEventType::Moved ? $spool->location : 'Imported']);
        }

        return 'updated';
    }

    /** Accepts the stored value ("on_spool") or the label ("On spool"). */
    private static function status(string $value): ?string
    {
        $value = Str::snake(trim($value));
        if ($value === '') {
            return null;
        }

        return SpoolStatus::tryFrom($value)?->value ?? $value;
    }

    private static function boolean(string $value): ?bool
    {
        return match (Str::lower(trim($value))) {
            '' => null,
            'yes', 'y', 'true', '1', 'ja', 'j' => true,
            default => false,
        };
    }

    /** Accepts "12.50", "12,50" and "€ 12,50". */
    private static function price(string $value): ?string
    {
        $value = preg_replace('/[^\d.,]/', '', $value);
        if ($value === '') {
            return null;
        }

        return str_contains($value, '.') ? str_replace(',', '', $value) : str_replace(',', '.', $value);
    }

    /** Undo ExportSpools::safeCell. */
    public static function unsafeCell(string $value): string
    {
        return preg_match("/^'[=+\\-@\t\r]/", $value) ? substr($value, 1) : $value;
    }
}
