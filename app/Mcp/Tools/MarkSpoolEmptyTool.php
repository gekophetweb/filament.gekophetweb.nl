<?php

namespace App\Mcp\Tools;

use App\Actions\MarkSpoolEmpty;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('mark_spool_empty')]
#[Description('Mark one of the user\'s spools as empty (remaining weight 0). Changes data: only call when the user clearly asked for it. The spool stays in history; nothing is deleted.')]
#[IsReadOnly(false)]
#[IsDestructive]
#[IsIdempotent]
class MarkSpoolEmptyTool extends SpoolTool
{
    protected bool $writes = true;

    protected function run(Request $request, User $user): Response
    {
        $request->validate(['uuid' => ['required', 'uuid']]);
        $spool = $this->findSpool($user, $request->get('uuid'));

        if (! $spool) {
            return Response::error('Spool not found.');
        }

        return Response::json($this->spoolData(app(MarkSpoolEmpty::class)->handle($spool)));
    }

    public function schema(JsonSchema $schema): array
    {
        return ['uuid' => $schema->string()->format('uuid')->required()];
    }
}
