<?php

namespace App\Http\Resources;

use App\Models\FilamentProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FilamentProduct */
class FilamentProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand?->name,
            'material' => $this->material?->name,
            'line' => $this->line,
            'article_name' => $this->article_name,
            'article_number' => $this->article_number,
            'color_hex' => $this->color_hex,
            'color_hex_2' => $this->color_hex_2,
            'color_family' => $this->color_family,
            'diameter' => $this->diameter,
            'nominal_weight' => $this->nominal_weight,
            'has_refill' => $this->has_refill,
            'product_url' => $this->product_url,
            'buy_url' => $this->buyUrl(),
            'boxed_count' => $this->whenHas('boxed_count'),
            'on_spool_count' => $this->whenHas('on_spool_count'),
            'owned_count' => $this->whenHas('owned_count'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
