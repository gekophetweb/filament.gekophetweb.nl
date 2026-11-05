<?php

namespace App\Http\Controllers\Api;

use App\Actions\AddSpool;
use App\Actions\RecordWeight;
use App\Actions\UpdateSpool;
use App\Enums\SpoolStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SpoolEventResource;
use App\Http\Resources\SpoolResource;
use App\Models\Spool;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SpoolController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(SpoolStatus::class)],
            'low' => ['nullable', 'boolean'],
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
        if ($request->boolean('low')) {
            $query->lowStock();
        }

        return SpoolResource::collection($query->paginate((int) ($filters['per_page'] ?? 50))->withQueryString());
    }

    public function show(Request $request, Spool $spool): SpoolResource
    {
        $this->authorize('view', $spool);

        return new SpoolResource($spool->load('product.brand', 'product.material'));
    }

    public function store(Request $request, AddSpool $add): JsonResponse
    {
        return (new SpoolResource($add->handle($request->user(), $request->all())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Spool $spool, UpdateSpool $update): SpoolResource
    {
        $this->authorize('update', $spool);

        return new SpoolResource($update->handle($spool, $request->all())->load('product.brand', 'product.material'));
    }

    public function destroy(Spool $spool): Response
    {
        $this->authorize('delete', $spool);
        $spool->delete(); // soft delete; never a hard delete through the API

        return response()->noContent();
    }

    public function weigh(Request $request, Spool $spool, RecordWeight $record): SpoolResource
    {
        $this->authorize('update', $spool);
        $data = $request->validate([
            'weight' => ['required', 'integer', 'min:0', 'max:20000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return new SpoolResource($record->handle($spool, $data['weight'], $data['note'] ?? null)->load('product.brand', 'product.material'));
    }

    public function events(Spool $spool): AnonymousResourceCollection
    {
        $this->authorize('view', $spool);

        return SpoolEventResource::collection($spool->events()->paginate(100));
    }
}
