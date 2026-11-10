<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Personal tokens exist only for the MCP endpoint (RF-12). The REST API is for the SPA session,
 * so a leaked token cannot be used against /api/v1 (smaller blast radius).
 */
class RejectMcpTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->currentAccessToken() instanceof PersonalAccessToken) {
            return ApiResponse::error('Este token solo sirve para el servidor MCP (/mcp).', 403);
        }

        return $next($request);
    }
}
