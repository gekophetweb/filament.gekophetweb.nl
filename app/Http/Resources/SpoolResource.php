<?php

namespace App\Http\Resources;

use App\Models\Spool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Spool */
class SpoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'product' => new FilamentProductResource($this->whenLoaded('product')),
            'status' => $this->status->value,
            'is_refill' => $this->is_refill,
            'initial_weight' => $this->initial_weight,
            'remaining_weight' => $this->remaining_weight,
            'remaining_percent' => $this->remainingPercent(),
            'is_low' => $this->isLow(),
            'purchased_at' => $this->purchased_at?->toDateString(),
            'price' => $this->price,
            'location' => $this->location,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
