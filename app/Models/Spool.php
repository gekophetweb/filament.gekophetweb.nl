<?php

namespace App\Models;

use App\Enums\SpoolStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'filament_product_id', 'status', 'is_refill', 'initial_weight', 'remaining_weight',
    'purchased_at', 'price', 'location', 'notes',
])]
class Spool extends Model
{
    use HasFactory, SoftDeletes;

    /** Remaining-weight threshold (grams) for "low stock". */
    public const LOW_STOCK_GRAMS = 200;

    protected static function booted(): void
    {
        static::creating(function (Spool $spool): void {
            $spool->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => SpoolStatus::class,
            'is_refill' => 'boolean',
            'initial_weight' => 'integer',
            'remaining_weight' => 'integer',
            'purchased_at' => 'date',
            'price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<FilamentProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(FilamentProduct::class, 'filament_product_id');
    }

    /** @return HasMany<SpoolEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SpoolEvent::class)->latest('created_at')->latest('id');
    }

    public function remainingPercent(): int
    {
        if ($this->initial_weight <= 0) {
            return 0;
        }

        return (int) max(0, min(100, round($this->remaining_weight / $this->initial_weight * 100)));
    }

    public function isLow(): bool
    {
        return ! in_array($this->status, [SpoolStatus::Empty, SpoolStatus::Archived], true)
            && $this->remaining_weight <= self::LOW_STOCK_GRAMS;
    }

    /** @param  Builder<Spool>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotIn('status', [SpoolStatus::Empty->value, SpoolStatus::Archived->value]);
    }

    /** @param  Builder<Spool>  $query */
    public function scopeLowStock(Builder $query): void
    {
        $query->active()->where('remaining_weight', '<=', self::LOW_STOCK_GRAMS);
    }
}
