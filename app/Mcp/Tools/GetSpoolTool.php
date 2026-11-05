<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_spool')]
#[Description('Get one of the user\'s spools by UUID, including its last 20 events.')]
#[IsReadOnly]
class GetSpoolTool extends SpoolTool
{
    protected function run(Request $request, User $user): Response
    {
        $request->validate(['uuid' => ['required', 'uuid']]);
        $spool = $this->findSpool($user, $request->get('uuid'));

        if (! $spool) {
            return Response::error('Spool not found.');
        }

        return Response::json([
            ...$this->spoolData($spool),
            'events' => $spool->events()->limit(20)->get()->map(fn ($e) => [
                'type' => $e->type->value,
                'weight_g' => $e->weight,
                'at' => $e->created_at->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['uuid' => $schema->string()->format('uuid')->required()];
    }
}
