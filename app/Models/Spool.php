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

/**
 * One physical spool (or refill) of a catalog product: boxed, on the spool holder, or used up.
 * The legacy initial_weight/remaining_weight columns are no longer used.
 */
#[Fillable([
    'filament_product_id', 'status', 'is_refill', 'purchased_at', 'price', 'location', 'notes',
])]
class Spool extends Model
{
    use HasFactory, SoftDeletes;

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

    /**
     * Spools that still hold filament: boxed or on the spool holder.
     *
     * @param  Builder<Spool>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', array_column(SpoolStatus::owned(), 'value'));
    }
}
