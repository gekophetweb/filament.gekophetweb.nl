<?php

namespace App\Mcp\Tools;

use App\Actions\RecordWeight;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('record_weight')]
#[Description('Record the remaining net filament weight (grams, without the spool) of one of the user\'s spools. 0 marks it empty.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
class RecordWeightTool extends SpoolTool
{
    protected bool $writes = true;

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate([
            'uuid' => ['required', 'uuid'],
            'remaining_grams' => ['required', 'integer', 'min:0', 'max:20000'],
        ]);

        $spool = $this->findSpool($user, $input['uuid']);
        if (! $spool) {
            return Response::error('Spool not found.');
        }

        return Response::json($this->spoolData(app(RecordWeight::class)->handle($spool, $input['remaining_grams'], 'via MCP')));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'uuid' => $schema->string()->format('uuid')->required(),
            'remaining_grams' => $schema->integer()->min(0)->max(20000)->required(),
        ];
    }
}
