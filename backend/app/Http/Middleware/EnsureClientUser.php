<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The portal is for client users only (D-037, CA-13.14): the team gets 403 on every portal route.
 */
class EnsureClientUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isClient()) {
            return ApiResponse::error('No tenés permiso para esta acción.', 403);
        }

        return $next($request);
    }
}
