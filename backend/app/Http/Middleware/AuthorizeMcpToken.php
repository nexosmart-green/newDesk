<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\ActivityContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * RF-12.03/05/06/07 for every MCP request (after auth:sanctum):
 *  - only personal tokens (never the web session cookie), of an active user;
 *  - team users only (a client user lives in the portal, CA-13.14);
 *  - `mcp.use`, plus the server's own permissions (e.g. `monitoring.view` for /mcp/monitoreo), evaluated
 *    against the CURRENT role — promotions/demotions apply without a new token;
 *  - activity is recorded with source = mcp and the token name;
 *  - 60 requests/minute per token, shared by every MCP server.
 */
class AuthorizeMcpToken
{
    public const PER_MINUTE = 60;

    public function __construct(private readonly ActivityContext $context) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user instanceof User || ! $token instanceof PersonalAccessToken || ! $user->isActive()) {
            return $this->deny('Token inválido, revocado o usuario inactivo.', 401);
        }

        if ($user->isClient()) {
            return $this->deny('Este token no es de un usuario del equipo.', 403);
        }

        if (! $user->hasPermission(Permission::McpUse)) {
            return $this->deny('Tu rol no tiene habilitado el uso de MCP.', 403);
        }
        foreach ($permissions as $permission) {
            if (! $user->hasPermission(Permission::from($permission))) {
                return $this->deny('Tu rol no permite usar este servidor MCP.', 403);
            }
        }

        $key = "mcp-token:{$token->id}";
        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return $this->deny('Demasiadas solicitudes: máximo '.self::PER_MINUTE.' por minuto. Esperá '.RateLimiter::availableIn($key).' s.', 429);
        }
        RateLimiter::hit($key, 60);

        $this->context->useMcp($token->name);

        return $next($request);
    }

    private function deny(string $message, int $status): Response
    {
        return response()->json(['error' => $message], $status);
    }
}
