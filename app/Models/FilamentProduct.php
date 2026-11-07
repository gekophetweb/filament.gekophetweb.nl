<?php

namespace App\Models;

use App\Enums\SpoolStatus;
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
     * Link to buy this filament: its product page when known, otherwise a store search.
     * Searching Bambu Lab's store by article number lands on exactly that product.
     */
    public function buyUrl(): ?string
    {
        if ($this->product_url) {
            return $this->product_url;
        }

        if ($this->brand?->name !== 'Bambu Lab') {
            return null;
        }

        $query = ctype_digit($this->article_number)
            ? $this->article_number
            : trim("{$this->material?->name} {$this->line} {$this->article_name}");

        return rtrim((string) config('services.bambulab.store_url'), '/').'/search?'.http_build_query(['q' => $query]);
    }

    /**
     * Adds the user's counts for this product: boxed_count, on_spool_count and
     * owned_count (both together).
     *
     * @param  Builder<FilamentProduct>  $query
     */
    public function scopeWithOwnedCount(Builder $query, User $user): void
    {
        $mine = fn (Builder $spools) => $spools->where('user_id', $user->id);

        $query->withCount([
            'spools as boxed_count' => fn (Builder $spools) => $mine($spools)->where('status', SpoolStatus::Boxed),
            'spools as on_spool_count' => fn (Builder $spools) => $mine($spools)->where('status', SpoolStatus::OnSpool),
            'spools as owned_count' => fn (Builder $spools) => $mine($spools)->active(),
        ]);
    }
}
