<?php

namespace App\Mcp\Tools;

use App\Models\McpAuditLog;
use App\Models\Spool;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Base for all spool tools:
 * - data is always resolved through the authenticated user, never an ID the model supplies alone;
 * - write tools need the spools:write token ability (hidden from read-only tokens);
 * - every call is written to the MCP audit log.
 */
abstract class SpoolTool extends Tool
{
    protected bool $writes = false;

    abstract protected function run(Request $request, User $user): Response;

    public function shouldRegister(Request $request): bool
    {
        return ! $this->writes || $this->canWrite($request->user());
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->audit(null, $request, 'unauthenticated', Response::error('Not authenticated.'));
        }

        if ($this->writes && ! $this->canWrite($user)) {
            return $this->audit($user, $request, 'forbidden', Response::error('This token is read-only.'));
        }

        try {
            $response = $this->run($request, $user);
        } catch (ValidationException $e) {
            $this->audit($user, $request, 'invalid', Response::error(''));
            throw $e;
        } catch (Throwable $e) {
            $this->audit($user, $request, 'error', Response::error(''));
            throw $e;
        }

        return $this->audit($user, $request, $response->isError() ? 'error' : 'ok', $response);
    }

    protected function findSpool(User $user, ?string $uuid): ?Spool
    {
        return $uuid ? $user->spools()->where('uuid', $uuid)->with('product.material', 'product.brand')->first() : null;
    }

    /** Spool as plain data. */
    protected function spoolData(Spool $spool): array
    {
        return [
            'uuid' => $spool->uuid,
            'article_name' => $spool->product->article_name,
            'article_number' => $spool->product->article_number,
            'material' => $spool->product->material->name,
            'line' => $spool->product->line,
            'color_hex' => $spool->product->color_hex,
            'status' => $spool->status->value, // boxed, on_spool, used_up or archived
        ];
    }

    private function canWrite(?object $user): bool
    {
        return $user instanceof User && $user->tokenCan('spools:write');
    }

    private function audit(?User $user, Request $request, string $status, Response $response): Response
    {
        McpAuditLog::create([
            'user_id' => $user?->id,
            'tool' => $this->name(),
            'arguments' => $request->all(),
            'status' => $status,
        ]);

        logger()->channel('mcp_audit')->info('mcp tool call', [
            'user_id' => $user?->id, 'tool' => $this->name(), 'status' => $status,
        ]);

        return $response;
    }
}
