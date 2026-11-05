<?php

namespace App\Models;

use App\Support\Color;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'brand_id', 'material_id', 'line', 'article_name', 'article_number',
    'color_hex', 'color_hex_2', 'color_family', 'diameter', 'nominal_weight',
    'has_refill', 'product_url', 'is_active',
])]
class FilamentProduct extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'diameter' => 'float',
            'nominal_weight' => 'integer',
            'has_refill' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** @return HasMany<Spool, $this> */
    public function spools(): HasMany
    {
        return $this->hasMany(Spool::class);
    }

    /** CSS background for the swatch: solid, or a gradient for dual colors. */
    public function swatchCss(): string
    {
        return $this->color_hex_2
            ? "linear-gradient(135deg, {$this->color_hex} 0%, {$this->color_hex_2} 100%)"
            : $this->color_hex;
    }

    public function labelColor(): string
    {
        return Color::contrastText($this->color_hex);
    }

    /**
     * Adds owned_count: the user's spools of this product that still hold filament.
     *
     * @param  Builder<FilamentProduct>  $query
     */
    public function scopeWithOwnedCount(Builder $query, User $user): void
    {
        $query->withCount(['spools as owned_count' => fn (Builder $q) => $q->where('user_id', $user->id)->active()]);
    }
}
