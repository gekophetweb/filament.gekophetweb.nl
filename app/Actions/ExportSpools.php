<?php

namespace App\Actions;

use App\Models\Spool;
use App\Models\User;

/**
 * Writes a user's spools as CSV that ImportSpools reads back. Deleted spools are left out.
 */
class ExportSpools
{
    /** @var list<string> */
    public const COLUMNS = [
        'id', 'article_number', 'filament', 'material', 'status', 'refill',
        'purchased_at', 'added_at',
    ];

    /**
     * Write the CSV, with a UTF-8 byte order mark so Excel reads accents correctly.
     *
     * @param  resource  $handle
     */
    public function write(User $user, $handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::COLUMNS, escape: '');

        $user->spools()->with('product.material')->orderBy('created_at')->orderBy('id')
            ->chunk(500, function ($spools) use ($handle): void {
                foreach ($spools as $spool) {
                    fputcsv($handle, array_map(self::safeCell(...), $this->row($spool)), escape: '');
                }
            });
    }

    /** @return list<string> */
    private function row(Spool $spool): array
    {
        return [
            $spool->uuid,
            $spool->product->article_number,
            $spool->product->article_name,
            $spool->product->material->name,
            $spool->status->value,
            $spool->is_refill ? 'yes' : 'no',
            $spool->purchased_at?->toDateString() ?? '',
            $spool->created_at->toDateString(),
        ];
    }

    /**
     * Keep spreadsheets from running a cell as a formula; ImportSpools strips the quote again.
     */
    public static function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
