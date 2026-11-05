<?php

use App\Mcp\Servers\SpoolServer;
use Laravel\Mcp\Facades\Mcp;

// MCP over HTTP at /mcp. Sanctum bearer tokens from the Devices page;
// IP allow-list and throttles run before token authentication.
Mcp::web('/mcp', SpoolServer::class)
    ->middleware(['ip:mcp', 'auth-failures', 'throttle-ip:api-ip', 'auth:sanctum', 'throttle:mcp']);
