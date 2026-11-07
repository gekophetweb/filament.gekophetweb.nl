<?php

namespace App\Http\Controllers\Api;

use App\Actions\AddSpool;
use App\Actions\MarkSpoolUsedUp;
use App\Actions\OpenSpool;
use App\Actions\RemoveSpool;
use App\Actions\UpdateSpool;
use App\Enums\SpoolStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SpoolEventResource;
use App\Http\Resources\SpoolResource;
use App\Models\Spool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SpoolController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(SpoolStatus::class)],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $request->user()->spools()->with('product.brand', 'product.material')->orderByDesc('updated_at');

        if (isset($filters['updated_since'])) {
            // Sync mode: include soft-deleted spools so the app can remove them.
            $query->withTrashed()->where('updated_at', '>', Carbon::parse($filters['updated_since'])->utc());
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return SpoolResource::collection($query->paginate((int) ($filters['per_page'] ?? 50))->withQueryString());
    }

    public function show(Request $request, Spool $spool): SpoolResource
    {
        $this->authorize('view', $spool);

        return new SpoolResource($spool->load('product.brand', 'product.material'));
    }

    /**
     * Add one spool, or with "amount" several identical ones; then "data" is a list.
     */
    public function store(Request $request, AddSpool $add): JsonResponse
    {
        if (! $request->has('amount')) {
            return (new SpoolResource($add->handle($request->user(), $request->all())))
                ->response()
                ->setStatusCode(201);
        }

        $amount = (int) $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.AddSpool::MAX_AMOUNT],
        ])['amount'];

        return SpoolResource::collection($add->handleMany($request->user(), $request->except('amount'), $amount))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Spool $spool, UpdateSpool $update): SpoolResource
    {
        $this->authorize('update', $spool);

        return new SpoolResource($update->handle($spool, $request->all())->load('product.brand', 'product.material'));
    }

    /** Boxed -> on the spool holder. */
    public function open(Request $request, Spool $spool, OpenSpool $open): SpoolResource
    {
        $this->authorize('update', $spool);

        return new SpoolResource($open->handle($spool, $this->note($request))->load('product.brand', 'product.material'));
    }

    public function usedUp(Request $request, Spool $spool, MarkSpoolUsedUp $usedUp): SpoolResource
    {
        $this->authorize('update', $spool);

        return new SpoolResource($usedUp->handle($spool, $this->note($request))->load('product.brand', 'product.material'));
    }

    /** Removed without counting as used (mistake, sold, given away). Soft delete only. */
    public function destroy(Request $request, Spool $spool, RemoveSpool $remove): Response
    {
        $this->authorize('delete', $spool);
        $remove->handle($spool, $this->note($request));

        return response()->noContent();
    }

    public function events(Spool $spool): AnonymousResourceCollection
    {
        $this->authorize('view', $spool);

        return SpoolEventResource::collection($spool->events()->paginate(100));
    }

    private function note(Request $request): ?string
    {
        return $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;
    }
}
