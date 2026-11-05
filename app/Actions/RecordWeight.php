<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Models\Spool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordWeight
{
    /**
     * Record the spool's remaining (net) filament weight in grams.
     */
    public function handle(Spool $spool, int $remainingGrams, ?string $note = null): Spool
    {
        if ($remainingGrams < 0 || $remainingGrams > 20000) {
            throw ValidationException::withMessages(['weight' => 'Weight must be between 0 and 20000 grams.']);
        }

        return DB::transaction(function () use ($spool, $remainingGrams, $note) {
            $spool->remaining_weight = min($remainingGrams, $spool->initial_weight);

            if ($spool->remaining_weight === 0) {
                $spool->status = SpoolStatus::Empty;
            } elseif ($spool->status === SpoolStatus::Sealed || $spool->status === SpoolStatus::Empty) {
                $spool->status = SpoolStatus::InUse;
            }

            $spool->save();

            $spool->events()->create([
                'type' => $spool->status === SpoolStatus::Empty ? SpoolEventType::Emptied : SpoolEventType::Weighed,
                'weight' => $spool->remaining_weight,
                'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            ]);

            return $spool;
        });
    }
}
