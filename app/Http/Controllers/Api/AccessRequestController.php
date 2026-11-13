<?php

namespace App\Http\Controllers\Api;

use App\Actions\HandleAccessRequest;
use App\Enums\AccessRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AccessRequestResource;
use App\Models\AccessRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * People who signed in on the token owner's share link, are not on the requester list, and asked to be added.
 */
class AccessRequestController extends Controller
{
    /** Pending ones by default, oldest first, like on Share & export. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(AccessRequestStatus::class)]])['status']
            ?? AccessRequestStatus::Pending->value;

        return AccessRequestResource::collection(
            $request->user()->accessRequests()->where('status', $status)->oldest()->limit(200)->get(),
        );
    }

    /** Adds the person to the requester list and emails them. */
    public function approve(Request $request, int $id, HandleAccessRequest $handle): AccessRequestResource
    {
        return new AccessRequestResource($handle->approve($this->find($request, $id)));
    }

    /** Declines quietly; no email. */
    public function decline(Request $request, int $id, HandleAccessRequest $handle): AccessRequestResource
    {
        return new AccessRequestResource($handle->decline($this->find($request, $id)));
    }

    /** Only the token owner's own access requests; anyone else's answers 404. */
    private function find(Request $request, int $id): AccessRequest
    {
        return $request->user()->accessRequests()->findOrFail($id);
    }
}
