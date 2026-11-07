<?php

namespace App\Actions;

use App\Enums\SpoolEventType;
use App\Models\Spool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UpdateSpool
{
    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'is_refill' => ['sometimes', 'boolean'],
            'purchased_at' => ['sometimes', 'nullable', 'date'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999'],
            'location' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
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
        $data = Validator::make($input, self::rules())->validate();

        return DB::transaction(function () use ($spool, $data) {
            $spool->fill($data);
            $moved = $spool->isDirty('location');
            $spool->save();

            if ($moved) {
                $spool->events()->create(['type' => SpoolEventType::Moved, 'note' => $spool->location]);
            }

            return $spool;
        });
    }
}
