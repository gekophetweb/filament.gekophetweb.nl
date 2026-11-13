<?php

namespace App\Http\Controllers\Api;

use App\Actions\SavePrintRequestComments;
use App\Enums\PrintRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PrintRequestResource;
use App\Models\PrintRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The print requests that came in through the token owner's share link, as in the Print requests inbox.
 */
class PrintRequestController extends Controller
{
    /** Folders as in the inbox: "open" still to handle (open or accepted), "unread", or "all". */
    public const FOLDERS = ['open', 'unread', 'all'];

    public const MAX_PER_PAGE = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'folder' => ['nullable', Rule::in(self::FOLDERS)],
            'status' => ['nullable', Rule::enum(PrintRequestStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $requests = $request->user()->printRequests()
            ->when(($filters['folder'] ?? null) === 'open', fn (Builder $query) => $query->whereIn('status', PrintRequestStatus::toHandle()))
            ->when(($filters['folder'] ?? null) === 'unread', fn (Builder $query) => $query->unread())
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->with('colors.material')
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 50))
            ->withQueryString();

        return PrintRequestResource::collection($requests);
    }

    public function show(PrintRequest $printRequest): PrintRequestResource
    {
        $this->authorize('view', $printRequest);

        return new PrintRequestResource($printRequest->load('colors.material'));
    }

    /**
     * Change the status, the reply (emailed to the requester when new or changed), the private note, or read/unread.
     */
    public function update(Request $request, PrintRequest $printRequest, SavePrintRequestComments $comments): PrintRequestResource
    {
        $this->authorize('update', $printRequest);

        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(PrintRequestStatus::class)],
            'reply' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'private_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'read' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['status'])) {
            $printRequest->update(['status' => $data['status']]);
        }

        if (isset($data['read'])) {
            $data['read'] ? $printRequest->markRead() : $printRequest->markUnread();
        }

        $emailedTo = null;
        if (array_key_exists('reply', $data) || array_key_exists('private_note', $data)) {
            $emailedTo = $comments->handle(
                $printRequest,
                array_key_exists('reply', $data) ? $data['reply'] : $printRequest->reply,
                array_key_exists('private_note', $data) ? $data['private_note'] : $printRequest->private_note,
            );
        }

        return (new PrintRequestResource($printRequest->load('colors.material')))
            ->additional(['meta' => ['reply_emailed_to' => $emailedTo]]);
    }

    /** Deletes the request and its STL file. */
    public function destroy(PrintRequest $printRequest): Response
    {
        $this->authorize('delete', $printRequest);
        $printRequest->delete();

        return response()->noContent();
    }

    /** The uploaded STL, always as a download. */
    public function stl(PrintRequest $printRequest): StreamedResponse
    {
        $this->authorize('view', $printRequest);
        abort_unless($printRequest->stl_path && Storage::disk('local')->exists($printRequest->stl_path), 404);

        return Storage::disk('local')->download(
            $printRequest->stl_path,
            $printRequest->stl_original_name ?: "{$printRequest->uuid}.stl",
            ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
