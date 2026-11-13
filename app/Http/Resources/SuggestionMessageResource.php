<?php

namespace App\Http\Resources;

use App\Models\SuggestionMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SuggestionMessage */
class SuggestionMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'from_owner' => $this->from_owner,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
