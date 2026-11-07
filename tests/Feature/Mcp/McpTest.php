<?php

use App\Enums\SpoolStatus;
use App\Mcp\Servers\SpoolServer;
use App\Mcp\Tools\AddSpoolTool;
use App\Mcp\Tools\GetSpoolTool;
use App\Mcp\Tools\ListSpoolsTool;
use App\Mcp\Tools\MarkSpoolUsedUpTool;
use App\Mcp\Tools\OpenSpoolTool;
use App\Mcp\Tools\RunningOutReportTool;
use App\Mcp\Tools\SearchCatalogTool;
use App\Models\FilamentProduct;
use App\Models\McpAuditLog;
use App\Models\Spool;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function mcpUser(array $abilities): User
{
    return Sanctum::actingAs(user(), $abilities);
}

it('searches the catalog', function () {
    $user = mcpUser(['spools:read']);
    FilamentProduct::factory()->create(['article_name' => 'Jade White']);

    SpoolServer::actingAs($user)->tool(SearchCatalogTool::class, ['q' => 'jade'])
        ->assertOk()
        ->assertSee('Jade White')
        ->assertSee('boxed_count');
});

it('only lists and reads the user\'s own spools', function () {
    $user = mcpUser(['spools:read']);
    $mine = Spool::factory()->for($user)->create();
    $theirs = Spool::factory()->create();

    SpoolServer::actingAs($user)->tool(ListSpoolsTool::class)
        ->assertSee($mine->uuid)
        ->assertDontSee($theirs->uuid);

    SpoolServer::actingAs($user)->tool(GetSpoolTool::class, ['uuid' => $theirs->uuid])
        ->assertHasErrors(['Spool not found.']);
});

it('cannot write another user\'s spool', function () {
    $user = mcpUser(['spools:read', 'spools:write']);
    $theirs = Spool::factory()->create();

    SpoolServer::actingAs($user)->tool(OpenSpoolTool::class, ['uuid' => $theirs->uuid])
        ->assertHasErrors(['Spool not found.']);
    SpoolServer::actingAs($user)->tool(MarkSpoolUsedUpTool::class, ['uuid' => $theirs->uuid])
        ->assertHasErrors(['Spool not found.']);

    expect($theirs->fresh()->status)->toBe(SpoolStatus::Boxed);
});

it('hides and refuses write tools for read-only tokens', function () {
    $user = mcpUser(['spools:read']);
    $spool = Spool::factory()->for($user)->create();

    SpoolServer::actingAs($user)->tool(OpenSpoolTool::class, ['uuid' => $spool->uuid])
        ->assertHasErrors();

    expect($spool->fresh()->status)->toBe(SpoolStatus::Boxed)
        ->and(McpAuditLog::where('tool', 'put_spool_on_holder')->value('status'))->toBeIn(['forbidden', 'error', null]);
});

it('runs write tools with a write token and audits them', function () {
    $user = mcpUser(['spools:read', 'spools:write']);
    $product = FilamentProduct::factory()->create(['article_name' => 'Jade White']);

    SpoolServer::actingAs($user)->tool(AddSpoolTool::class, ['product_id' => $product->id])->assertOk();
    $spool = $user->spools()->sole();

    SpoolServer::actingAs($user)->tool(OpenSpoolTool::class, ['uuid' => $spool->uuid])->assertOk()->assertSee('on_spool');
    SpoolServer::actingAs($user)->tool(RunningOutReportTool::class)->assertSee('Jade White');
    SpoolServer::actingAs($user)->tool(MarkSpoolUsedUpTool::class, ['uuid' => $spool->uuid])->assertOk();

    expect($spool->fresh()->status)->toBe(SpoolStatus::UsedUp)
        ->and(McpAuditLog::where('user_id', $user->id)->pluck('status', 'tool')->all())
        ->toMatchArray(['add_spool' => 'ok', 'put_spool_on_holder' => 'ok', 'mark_spool_used_up' => 'ok']);
});

it('leaves filaments with a boxed backup out of the running-out report', function () {
    $user = mcpUser(['spools:read']);
    $covered = FilamentProduct::factory()->create(['article_name' => 'Covered Grey']);
    Spool::factory()->for($user)->for($covered, 'product')->onSpool()->create();
    Spool::factory()->for($user)->for($covered, 'product')->create();

    SpoolServer::actingAs($user)->tool(RunningOutReportTool::class)->assertOk()->assertDontSee('Covered Grey');
});

it('validates tool input', function () {
    $user = mcpUser(['spools:read', 'spools:write']);

    SpoolServer::actingAs($user)->tool(OpenSpoolTool::class, ['uuid' => 'not-a-uuid'])
        ->assertHasErrors();
});

it('requires a token on the HTTP endpoint', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
});

it('serves tools/list over HTTP with a token and hides write tools for read tokens', function () {
    $token = user()->createToken('mcp', ['spools:read'])->plainTextToken;

    $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], [
        'Authorization' => "Bearer {$token}",
        'Accept' => 'application/json, text/event-stream',
    ])->assertOk();

    expect($response->getContent())->toContain('search_catalog')->toContain('running_out_report')
        ->not->toContain('put_spool_on_holder')->not->toContain('mark_spool_used_up');
});
