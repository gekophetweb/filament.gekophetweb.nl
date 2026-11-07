<?php

namespace App\Mcp\Tools;

use App\Actions\SearchCatalog;
use App\Http\Resources\FilamentProductResource;
use App\Models\User;
use App\Support\Color;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_catalog')]
#[Description('Search the filament catalog by name/SKU, material, line, color family and ownership. Returns up to 50 products with how many spools the user has boxed and on the spool holder, and a buy link.')]
#[IsReadOnly]
class SearchCatalogTool extends SpoolTool
{
    protected function run(Request $request, User $user): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'material' => ['nullable', 'string', 'max:50'],
            'line' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'in:'.implode(',', Color::families())],
            'owned' => ['nullable', Rule::in(SearchCatalog::OWNED_FILTERS)],
        ]);

        $products = app(SearchCatalog::class)->query($user, $filters)->limit(50)->get();

        return Response::json(FilamentProductResource::collection($products)->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Text to match in the article name or SKU.'),
            'material' => $schema->string()->description('Material, e.g. PLA, PETG, ABS.'),
            'line' => $schema->string()->description('Product line, e.g. Basic, Matte, Silk.'),
            'color' => $schema->string()->enum(Color::families())->description('Color family.'),
            'owned' => $schema->string()->enum(SearchCatalog::OWNED_FILTERS)->description('1 = owned (boxed or on spool), 0 = not owned, boxed, on_spool, running_out = on spool with no boxed spool left.'),
        ];
    }
}
