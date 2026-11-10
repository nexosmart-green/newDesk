<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RF-01.02: an Admin without 2FA can only reach /me, logout and the 2FA setup until they enable it.
 */
class EnsureTwoFactorWhenRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            return ApiResponse::error(
                'Activá la verificación en dos pasos para continuar.',
                403,
                data: ['code' => 'two_factor_setup_required'],
            );
        }

        return $next($request);
    }
}
