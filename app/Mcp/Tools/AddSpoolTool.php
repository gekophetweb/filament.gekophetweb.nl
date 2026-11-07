<?php

namespace App\Mcp\Tools;

use App\Actions\AddSpool;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('add_spool')]
#[Description('Add one or more boxed spools of a catalog product to the user\'s inventory. Use search_catalog first to find the product id.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
class AddSpoolTool extends SpoolTool
{
    protected bool $writes = true;

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate([
            'product_id' => ['required', 'integer'],
            'is_refill' => ['nullable', 'boolean'],
            'amount' => ['nullable', 'integer', 'min:1', 'max:'.AddSpool::MAX_AMOUNT],
        ]);

        $spoolInput = array_filter([
            'filament_product_id' => $input['product_id'],
            'is_refill' => $input['is_refill'] ?? null,
        ], fn ($v) => $v !== null);

        if (! isset($input['amount'])) {
            return Response::json($this->spoolData(app(AddSpool::class)->handle($user, $spoolInput)));
        }

        $spools = app(AddSpool::class)->handleMany($user, $spoolInput, (int) $input['amount']);

        return Response::json(['added' => $spools->count(), 'spools' => $spools->map($this->spoolData(...))->all()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'product_id' => $schema->integer()->description('Catalog product id from search_catalog.')->required(),
            'is_refill' => $schema->boolean()->description('True for a refill without a spool.'),
            'amount' => $schema->integer()->min(1)->max(AddSpool::MAX_AMOUNT)
                ->description('How many identical spools to add, 1-'.AddSpool::MAX_AMOUNT.'. Leave out for one; with it the result lists every new spool.'),
        ];
    }
}
