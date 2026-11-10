<?php

namespace App\Models;

use App\Enums\PrintRequestStatus;
use App\Support\FilamentEstimate;
use App\Support\StlMeasurement;
use Database\Factories\PrintRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A request to print something, sent by a visitor of the owner's share link.
 * Holds an uploaded STL (on the local disk), a link to a model, or both.
 */
#[Fillable([
    'requester_name', 'requester_contact', 'model_url', 'stl_path', 'stl_original_name', 'stl_size', 'notes', 'status',
])]
class PrintRequest extends Model
{
    /** @use HasFactory<PrintRequestFactory> */
    use HasFactory;

    /** Most filaments (colors) one request may ask for. */
    public const MAX_COLORS = 4;

    protected static function booted(): void
    {
        static::creating(function (PrintRequest $printRequest): void {
            $printRequest->uuid ??= (string) Str::uuid();
        });

        static::deleted(function (PrintRequest $printRequest): void {
            if ($printRequest->stl_path) {
                Storage::disk('local')->delete($printRequest->stl_path);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => PrintRequestStatus::class,
            'stl_size' => 'integer',
            'stl_volume' => 'float',
            'stl_dimensions' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * About how much filament the STL takes in the picked colors' material(s), or null without a measured STL.
     */
    public function filamentEstimate(): ?FilamentEstimate
    {
        if (! $this->stl_volume) {
            return null;
        }

        $materials = $this->colors->loadMissing('material')->map(fn (FilamentProduct $color) => $color->material->name)->all();

        return FilamentEstimate::forVolume($this->stl_volume, $materials);
    }

    /** The STL's size, for example "42 × 30 × 18 mm", or null when it was not measured. */
    public function dimensionsLabel(): ?string
    {
        if (! is_array($this->stl_dimensions) || count($this->stl_dimensions) !== 3) {
            return null;
        }

        return collect($this->stl_dimensions)->map(fn ($size) => rtrim(rtrim(number_format((float) $size, 1, '.', ''), '0'), '.'))->join(' × ').' mm';
    }

    /** Whether the STL's size suggests it was not exported in millimetres, so the estimate is off. */
    public function looksMisScaled(): bool
    {
        return is_array($this->stl_dimensions) && count($this->stl_dimensions) === 3
            && StlMeasurement::looksMisScaled(array_map('floatval', $this->stl_dimensions));
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markRead(): void
    {
        if ($this->isUnread()) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function markUnread(): void
    {
        $this->forceFill(['read_at' => null])->save();
    }

    /** @param  Builder<PrintRequest>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The filaments the requester picked, in the order they picked them.
     *
     * @return BelongsToMany<FilamentProduct, $this>
     */
    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(FilamentProduct::class)->withPivot('position')->orderByPivot('position');
    }
}
