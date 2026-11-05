<?php

namespace App\Mcp\Tools;

use App\Models\Spool;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('low_stock_report')]
#[Description('Spools of the user with little filament left (at or below the threshold, default 200 g), lowest first.')]
#[IsReadOnly]
class LowStockReportTool extends SpoolTool
{
    protected function run(Request $request, User $user): Response
    {
        $threshold = (int) ($request->validate(['threshold_grams' => ['nullable', 'integer', 'min:1', 'max:5000']])['threshold_grams'] ?? Spool::LOW_STOCK_GRAMS);

        $spools = $user->spools()->active()
            ->where('remaining_weight', '<=', $threshold)
            ->with('product.material', 'product.brand')
            ->orderBy('remaining_weight')
            ->get();

        return Response::json(['threshold_grams' => $threshold, 'spools' => $spools->map(fn ($s) => $this->spoolData($s))->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['threshold_grams' => $schema->integer()->min(1)->max(5000)->description('Default 200.')];
    }
}
