<?php

use App\Http\Middleware\AuthorizeMcpToken;
use App\Mcp\Servers\MonitoreoServer;
use App\Mcp\Servers\NewDeskServer;
use Laravel\Mcp\Facades\Mcp;

// RF-12.01: Streamable HTTP. Bearer token in the Authorization header only (never in the query string).
// The same personal token opens both servers; monitoreo's also needs `monitoring.view` (RF-13.10).
Mcp::web('/mcp', NewDeskServer::class)
    ->middleware(['auth:sanctum', AuthorizeMcpToken::class]);
Mcp::web('/mcp/monitoreo', MonitoreoServer::class)
    ->middleware(['auth:sanctum', AuthorizeMcpToken::class.':monitoring.view']);
