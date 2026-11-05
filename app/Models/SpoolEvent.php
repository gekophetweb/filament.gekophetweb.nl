<?php

namespace App\Models;

use App\Enums\SpoolEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'weight', 'note'])]
class SpoolEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => SpoolEventType::class,
            'weight' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Spool, $this> */
    public function spool(): BelongsTo
    {
        return $this->belongsTo(Spool::class);
    }
}
