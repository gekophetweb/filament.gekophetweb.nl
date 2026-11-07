<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Models\Spool;
use Illuminate\Support\Facades\DB;

class RemoveSpool
{
    /**
     * Remove a spool from the inventory (added by mistake, sold, given away) without it
     * counting as used. Soft delete: the spool and its history stay in the database.
     */
    public function handle(Spool $spool, ?string $note = null): void
    {
        DB::transaction(function () use ($spool, $note) {
            $spool->events()->create(['type' => SpoolEventType::Removed, 'note' => $note]);
            $spool->delete();
        });
    }
}
