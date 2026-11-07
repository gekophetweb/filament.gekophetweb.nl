<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Enums\SpoolStatus;
use App\Models\Spool;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AddSpool
{
    /** Most spools one call may add. */
    public const MAX_AMOUNT = 50;

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'filament_product_id' => ['required', 'integer', Rule::exists('filament_products', 'id')],
            'status' => ['sometimes', Rule::enum(SpoolStatus::class)->only(SpoolStatus::owned())],
            'is_refill' => ['sometimes', 'boolean'],
            'purchased_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Add several identical spools at once, all or none.
     *
     * @param  array<string, mixed>  $input
     * @return Collection<int, Spool>
     */
    public function handleMany(User $user, array $input, int $amount): Collection
    {
        if ($amount < 1 || $amount > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('The amount must be between 1 and '.self::MAX_AMOUNT.'.');
        }

        return DB::transaction(fn () => Collection::times($amount, fn () => $this->handle($user, $input)));
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
