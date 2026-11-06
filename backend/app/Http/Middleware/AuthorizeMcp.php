<?php

namespace App\Http\Middleware;

use App\Domain\Shared\Activity\ActivityContext;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * An MCP endpoint (RF-12): only with a personal token (Authorization: Bearer), only while the token's
 * owner holds the given permissions in their CURRENT role (RF-12.03, checked on every call), and every
 * change the tools make is recorded as done through MCP with the token's name (RF-12.06).
 */
class AuthorizeMcp
{
    public function __construct(private readonly ActivityContext $activity) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return ApiResponse::error('No autenticado.', 401);
        }
        foreach ($permissions as $permission) {
            if (! $user->can($permission)) {
                return ApiResponse::error('Tu rol no permite usar este MCP.', 403);
            }
        }
        $this->activity->useMcp($token->name);

        return $next($request);
    }
}
