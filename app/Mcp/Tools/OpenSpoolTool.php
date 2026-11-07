<?php

namespace App\Mcp\Tools;

use App\Actions\OpenSpool;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('put_spool_on_holder')]
#[Description('Take one of the user\'s boxed spools out of its box and put it on the spool holder (status boxed -> on_spool). Changes data: only call when the user asked for it.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
class OpenSpoolTool extends SpoolTool
{
    protected bool $writes = true;

    protected function run(Request $request, User $user): Response
    {
        $request->validate(['uuid' => ['required', 'uuid']]);
        $spool = $this->findSpool($user, $request->get('uuid'));

        if (! $spool) {
            return Response::error('Spool not found.');
        }

        return Response::json($this->spoolData(app(OpenSpool::class)->handle($spool, 'via MCP')));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['uuid' => $schema->string()->format('uuid')->required()];
    }
}
