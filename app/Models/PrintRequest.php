<?php

namespace App\Models;

use App\Enums\PrintRequestStatus;
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
            'read_at' => 'datetime',
        ];
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
