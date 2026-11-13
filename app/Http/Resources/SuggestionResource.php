<?php

namespace App\Http\Resources;

use App\Models\Suggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Suggestion */
class SuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'requester_name' => $this->requester_name,
            'requester_email' => $this->requester_email,
            'body' => $this->body,
            'messages_count' => $this->whenCounted('messages'),
            'messages' => SuggestionMessageResource::collection($this->whenLoaded('messages')),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
