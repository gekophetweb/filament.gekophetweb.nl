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
            'purchased_at' => $this->purchased_at?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
