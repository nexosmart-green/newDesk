<?php

use App\Http\Middleware\AuthorizeMcpToken;
use App\Mcp\Servers\NewDeskServer;
use Laravel\Mcp\Facades\Mcp;

// RF-12.01: Streamable HTTP at /mcp. Bearer token in the Authorization header only (never in the query string).
Mcp::web('/mcp', NewDeskServer::class)
    ->middleware(['auth:sanctum', AuthorizeMcpToken::class]);
