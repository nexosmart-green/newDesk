<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client users only reach the portal (RF-13.07, CA-13.14): every team route refuses them.
 */
class EnsureTeamUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isClient()) {
            return ApiResponse::error('No tenés permiso para esta acción.', 403);
        }

        return $next($request);
    }
}
