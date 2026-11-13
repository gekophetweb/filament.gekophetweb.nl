<?php

namespace App\Http\Controllers\Api;

use App\Actions\ReplyToSuggestion;
use App\Enums\SuggestionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SuggestionMessageResource;
use App\Http\Resources\SuggestionResource;
use App\Models\Suggestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The suggestions that came in through the token owner's share link, with their conversation.
 */
class SuggestionController extends Controller
{
    /** Folders as in the inbox: "open" still to handle (open or planned), "unread", or "all". */
    public const FOLDERS = ['open', 'unread', 'all'];

    public const MAX_PER_PAGE = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'folder' => ['nullable', Rule::in(self::FOLDERS)],
            'status' => ['nullable', Rule::enum(SuggestionStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $suggestions = $request->user()->suggestions()
            ->when(($filters['folder'] ?? null) === 'open', fn (Builder $query) => $query->toHandle())
            ->when(($filters['folder'] ?? null) === 'unread', fn (Builder $query) => $query->unread())
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->withCount('messages')
            ->latest('updated_at')
            ->paginate((int) ($filters['per_page'] ?? 50))
            ->withQueryString();

        return SuggestionResource::collection($suggestions);
    }

    public function show(Request $request, string $uuid): SuggestionResource
    {
        return new SuggestionResource($this->find($request, $uuid)->load('messages')->loadCount('messages'));
    }

    /** Change the status, or mark it read or unread. */
    public function update(Request $request, string $uuid): SuggestionResource
    {
        $suggestion = $this->find($request, $uuid);
        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(SuggestionStatus::class)],
            'read' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['status'])) {
            $suggestion->forceFill(['status' => $data['status']])->save();
        }

        if (isset($data['read'])) {
            $data['read'] ? $suggestion->markRead() : $suggestion->markUnread();
        }

        return new SuggestionResource($suggestion->load('messages')->loadCount('messages'));
    }

    /** Reply in the conversation; the requester gets it by email. */
    public function reply(Request $request, string $uuid, ReplyToSuggestion $replyTo): JsonResponse
    {
        $suggestion = $this->find($request, $uuid);
        $body = $request->validate(['body' => ['required', 'string', 'max:'.Suggestion::MAX_LENGTH]])['body'];

        return (new SuggestionMessageResource($replyTo->handle($suggestion, $body)))
            ->additional(['meta' => ['emailed_to' => $suggestion->requester_email]])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, string $uuid): Response
    {
        $this->find($request, $uuid)->delete();

        return response()->noContent();
    }

    /** Only the token owner's own suggestions; anyone else's answers 404. */
    private function find(Request $request, string $uuid): Suggestion
    {
        return $request->user()->suggestions()->where('uuid', $uuid)->firstOrFail();
    }
}
