<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
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
            'status' => ['sometimes', Rule::enum(SpoolStatus::class)->only(SpoolStatus::owned())],
            'is_refill' => ['sometimes', 'boolean'],
            'purchased_at' => ['sometimes', 'nullable', 'date'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Add a spool to the user's inventory, boxed unless a status says otherwise.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $user, array $input): Spool
    {
        $data = Validator::make($input, self::rules())->validate();
        $data['status'] ??= SpoolStatus::Boxed->value;

        return DB::transaction(function () use ($user, $data) {
            $spool = new Spool($data);
            $spool->user()->associate($user);
            $spool->save();

            $spool->events()->create(['type' => SpoolEventType::Added]);

            return $spool->load('product.brand', 'product.material');
        });
    }
}
