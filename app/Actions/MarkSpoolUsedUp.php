<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Models\Spool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkSpoolUsedUp
{
    /**
     * Mark a spool as used up. The spool stays in the history; nothing is deleted.
     */
    public function handle(Spool $spool, ?string $note = null): Spool
    {
        if (! in_array($spool->status, SpoolStatus::owned(), true)) {
            throw ValidationException::withMessages(['status' => 'This spool is already used up.']);
        }

        return DB::transaction(function () use ($spool, $note) {
            $spool->forceFill(['status' => SpoolStatus::UsedUp])->save();
            $spool->events()->create(['type' => SpoolEventType::UsedUp, 'note' => $note]);

            return $spool;
        });
    }
}
