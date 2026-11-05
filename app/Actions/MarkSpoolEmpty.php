<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Models\Spool;
use Illuminate\Support\Facades\DB;

class MarkSpoolEmpty
{
    public function handle(Spool $spool): Spool
    {
        return DB::transaction(function () use ($spool) {
            $spool->forceFill(['remaining_weight' => 0, 'status' => SpoolStatus::Empty])->save();
            $spool->events()->create(['type' => SpoolEventType::Emptied, 'weight' => 0]);

            return $spool;
        });
    }
}
