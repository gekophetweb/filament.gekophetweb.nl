<?php

namespace App\Http\Controllers\Api;

use App\Actions\SearchCatalog;
use App\Http\Controllers\Controller;
use App\Http\Resources\FilamentProductResource;
use App\Models\FilamentProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(Request $request, SearchCatalog $search): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'material' => ['nullable', 'string', 'max:50'],
            'brand' => ['nullable', 'string', 'max:50'],
            'line' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'owned' => ['nullable', Rule::in(SearchCatalog::OWNED_FILTERS)],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.SearchCatalog::MAX_PER_PAGE],
        ]);

        return FilamentProductResource::collection(
            $search->paginate($request->user(), $filters, (int) ($filters['per_page'] ?? 50))->withQueryString()
        );
    }

    public function show(Request $request, FilamentProduct $product): FilamentProductResource
    {
        $product = FilamentProduct::query()
            ->with(['brand', 'material'])
            ->withOwnedCount($request->user())
            ->findOrFail($product->id);

        return new FilamentProductResource($product);
    }
}
