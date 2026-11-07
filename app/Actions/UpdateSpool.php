<?php

namespace App\Actions;

use App\Models\Spool;
use Illuminate\Support\Facades\Validator;

class UpdateSpool
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'is_refill' => ['sometimes', 'boolean'],
            'purchased_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Update descriptive fields. Status changes go through OpenSpool, MarkSpoolUsedUp
     * and RemoveSpool so they always leave an event behind.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(Spool $spool, array $input): Spool
    {
        $spool->fill(Validator::make($input, self::rules())->validate())->save();

        return $spool;
    }
}
