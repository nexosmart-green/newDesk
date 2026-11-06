<?php

use App\Http\Middleware\AuthorizeMcp;
use App\Mcp\Servers\MonitoreoServer;
use Laravel\Mcp\Facades\Mcp;

// MCP servers (RF-12, RF-13.10). They take only a personal token in `Authorization: Bearer` (no session):
// the permissions are those of the owner's current role, checked on every call.
Mcp::web('/mcp/monitoreo', MonitoreoServer::class)
    ->middleware(['auth:sanctum', 'active', 'team', AuthorizeMcp::class.':mcp.use,monitoring.view', 'throttle:mcp']);
