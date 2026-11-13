<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in the conversation on a suggestion: a reply from the owner, or an answer from the requester.
 */
#[Fillable(['from_owner', 'body'])]
class SuggestionMessage extends Model
{
    protected function casts(): array
    {
        return [
            'from_owner' => 'boolean',
        ];
    }

    /** @return BelongsTo<Suggestion, $this> */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }
}
