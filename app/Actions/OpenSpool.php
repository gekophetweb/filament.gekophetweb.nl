<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Models\Spool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenSpool
{
    /**
     * Take a boxed spool out of its box and put it on the spool holder.
     */
    public function handle(Spool $spool, ?string $note = null): Spool
    {
        if ($spool->status !== SpoolStatus::Boxed) {
            throw ValidationException::withMessages(['status' => 'Only a boxed spool can be put on the spool holder.']);
        }

        return DB::transaction(function () use ($spool, $note) {
            $spool->forceFill(['status' => SpoolStatus::OnSpool])->save();
            $spool->events()->create(['type' => SpoolEventType::Opened, 'note' => $note]);

            return $spool;
        });
    }
}
