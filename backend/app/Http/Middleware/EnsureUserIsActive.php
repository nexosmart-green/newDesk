<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated user loses access immediately, for open sessions and MCP tokens alike
 * (RF-01.10, RF-12.03).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isActive()) {
            return ApiResponse::error('Tu usuario está dado de baja. Si creés que es un error, hablá con un administrador.', 401);
        }

        return $next($request);
    }
}
