<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use Database\Factories\AccessRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone signed in on the owner's share link, is not on the requester list, and asked to be added.
 * The email is the verified one they signed in with.
 */
#[Fillable(['email', 'name', 'message'])]
class AccessRequest extends Model
{
    /** @use HasFactory<AccessRequestFactory> */
    use HasFactory;

    public const MAX_MESSAGE_LENGTH = 500;

    /** Same as the column default, so a request created in this request has it too. */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => AccessRequestStatus::class,
        ];
    }

    /** @param  Builder<AccessRequest>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', AccessRequestStatus::Pending);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
