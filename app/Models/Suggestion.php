<?php

namespace App\Models;

use App\Enums\SuggestionStatus;
use Database\Factories\SuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An idea or piece of feedback a signed-in requester sent through the owner's share link.
 * The requester's email is the verified one they signed in with.
 */
#[Fillable(['requester_name', 'requester_email', 'body'])]
class Suggestion extends Model
{
    /** @use HasFactory<SuggestionFactory> */
    use HasFactory;

    public const MAX_LENGTH = 2000;

    /** Same as the column default, so a suggestion created in this request has it too. */
    protected $attributes = [
        'status' => 'open',
    ];

    protected static function booted(): void
    {
        static::creating(function (Suggestion $suggestion): void {
            $suggestion->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => SuggestionStatus::class,
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

    /** @param  Builder<Suggestion>  $query */
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
     * Replies and answers after the suggestion itself, oldest first.
     *
     * @return HasMany<SuggestionMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SuggestionMessage::class)->oldest()->orderBy('id');
    }

    /**
     * Add a message to the conversation. An answer from the requester marks the suggestion unread for the owner.
     */
    public function addMessage(string $body, bool $fromOwner): SuggestionMessage
    {
        $message = $this->messages()->create(['from_owner' => $fromOwner, 'body' => trim($body)]);

        if (! $fromOwner) {
            $this->markUnread();
        }

        $this->touch();

        return $message;
    }

    /** @param  Builder<Suggestion>  $query */
    public function scopeToHandle(Builder $query): void
    {
        $query->whereIn('status', SuggestionStatus::toHandle());
    }
}
