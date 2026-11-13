<?php

namespace App\Http\Resources;

use App\Models\FilamentProduct;
use App\Models\PrintRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrintRequest */
class PrintRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $estimate = $this->filamentEstimate();

        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'requester_name' => $this->requester_name,
            'requester_email' => $this->requester_email,
            'model_url' => $this->model_url,
            'stl' => $this->stl_path ? [
                'name' => $this->stl_original_name,
                'size' => $this->stl_size,
                'dimensions' => $this->stl_dimensions,
                'volume' => $this->stl_volume,
                'download_url' => url("/api/v1/print-requests/{$this->uuid}/stl"),
            ] : null,
            'estimate' => $estimate ? [
                'grams_low' => $estimate->lowGrams(),
                'grams_high' => $estimate->highGrams(),
                'label' => $estimate->label(),
                'price_label' => $estimate->priceLabel(),
                'looks_mis_scaled' => $this->looksMisScaled(),
            ] : null,
            'colors' => $this->colors->map(fn (FilamentProduct $color) => [
                'id' => $color->id,
                'article_name' => $color->article_name,
                'article_number' => $color->article_number,
                'material' => $color->material?->name,
                'color_hex' => $color->color_hex,
            ])->all(),
            'notes' => $this->notes,
            'reply' => $this->reply,
            'private_note' => $this->private_note,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
