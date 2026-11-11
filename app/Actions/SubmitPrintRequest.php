<?php

namespace App\Actions;

use App\Models\FilamentProduct;
use App\Models\PrintRequest;
use App\Models\User;
use App\Notifications\PrintRequestReceived;
use App\Support\StlMeasurement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Store a print request a visitor sent through the owner's share link.
 */
class SubmitPrintRequest
{
    /** Folder on the local (private) disk that holds uploaded STL files. */
    public const STL_DIRECTORY = 'print-requests';

    /**
     * Colors can only be filaments the share link shows, so visitors pick from what is in stock.
     *
     * @return array<string, mixed>
     */
    public static function rules(User $owner): array
    {
        $maxKilobytes = (int) config('access.print_request_max_upload_mb') * 1024;

        return [
            'requester_name' => ['required', 'string', 'max:100'],
            'requester_contact' => ['nullable', 'string', 'max:255'],
            'stl' => ['nullable', 'required_without:model_url', 'file', 'extensions:stl', "max:{$maxKilobytes}"],
            'model_url' => ['nullable', 'required_without:stl', 'url:http,https', 'max:2048'],
            'colors' => ['nullable', 'array', 'max:'.PrintRequest::MAX_COLORS],
            'colors.*' => ['integer', 'distinct', Rule::in(FilamentProduct::query()->inStockFor($owner)->pluck('id'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  string  $requesterEmail  The requester's verified sign-in email; it is also the contact.
     */
    public function handle(User $owner, array $input, string $requesterEmail): PrintRequest
    {
        $input['requester_contact'] = $requesterEmail;

        $data = Validator::make($input, self::rules($owner), [
            'stl.required_without' => 'Upload an STL file or paste a link to the model.',
            'model_url.required_without' => 'Paste a link to the model or upload an STL file.',
            'colors.max' => 'Pick at most '.PrintRequest::MAX_COLORS.' colors.',
            'colors.*.in' => 'Pick colors from the list.',
        ])->validate();

        $printRequest = new PrintRequest([
            'requester_name' => $data['requester_name'],
            'requester_contact' => $data['requester_contact'] ?? null,
            'model_url' => $data['model_url'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        $printRequest->uuid = (string) Str::uuid();
        $printRequest->requester_email = Str::lower($requesterEmail);
        $printRequest->user()->associate($owner);

        $stl = $data['stl'] ?? null;
        if ($stl instanceof UploadedFile) {
            // Measured here, from the upload itself, so the estimate is there for the owner's email.
            $measurement = StlMeasurement::fromFile((string) $stl->getRealPath());
            $printRequest->stl_volume = $measurement?->volume;
            $printRequest->stl_dimensions = $measurement ? array_map(fn (float $size) => round($size, 1), $measurement->dimensions) : null;

            $printRequest->fill([
                'stl_path' => $stl->storeAs(self::STL_DIRECTORY, "{$printRequest->uuid}.stl", 'local'),
                'stl_original_name' => Str::limit(basename($stl->getClientOriginalName()), 250, ''),
                'stl_size' => $stl->getSize(),
            ]);
        }

        try {
            DB::transaction(function () use ($printRequest, $data) {
                $printRequest->save();
                $printRequest->colors()->attach(collect(array_values($data['colors'] ?? []))
                    ->mapWithKeys(fn (int|string $id, int $position) => [(int) $id => ['position' => $position]])
                    ->all());
            });
        } catch (\Throwable $e) {
            if ($printRequest->stl_path) {
                Storage::disk('local')->delete($printRequest->stl_path);
            }

            throw $e;
        }

        if ($owner->emails_print_requests && $owner->email) {
            $owner->notify(new PrintRequestReceived($printRequest));
        }

        return $printRequest;
    }
}
