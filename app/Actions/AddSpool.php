<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Models\FilamentProduct;
use App\Models\Spool;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AddSpool
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'filament_product_id' => ['required', 'integer', Rule::exists('filament_products', 'id')],
            'status' => ['sometimes', Rule::enum(SpoolStatus::class)],
            'is_refill' => ['sometimes', 'boolean'],
            'initial_weight' => ['sometimes', 'integer', 'min:1', 'max:20000'],
            'remaining_weight' => ['sometimes', 'integer', 'min:0', 'max:20000'],
            'purchased_at' => ['sometimes', 'nullable', 'date'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @param  array<string, mixed>  $input */
    public function handle(User $user, array $input): Spool
    {
        $data = Validator::make($input, self::rules())->validate();

        $product = FilamentProduct::findOrFail($data['filament_product_id']);
        $data['initial_weight'] ??= $product->nominal_weight;
        $data['remaining_weight'] = min($data['remaining_weight'] ?? $data['initial_weight'], $data['initial_weight']);
        $data['status'] ??= SpoolStatus::Sealed->value;

        return DB::transaction(function () use ($user, $data) {
            $spool = new Spool($data);
            $spool->user()->associate($user);
            $spool->save();

            $spool->events()->create([
                'type' => SpoolEventType::Added,
                'weight' => $spool->remaining_weight,
            ]);

            return $spool->load('product.brand', 'product.material');
        });
    }
}
