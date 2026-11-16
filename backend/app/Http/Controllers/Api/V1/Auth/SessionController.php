<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Auth\SessionLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\CurrentUserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sanctum SPA (cookie) login. MCP uses personal tokens instead (RF-12).
 */
class SessionController extends Controller
{
    public function __construct(private readonly SessionLogin $sessionLogin) {}

    public function store(LoginRequest $request): JsonResponse
    {
        // Only active users can log in (RF-01.10); invited users have no password yet.
        $user = $this->sessionLogin->findActiveUserByCredentials($request->string('email'), $request->string('password'));
        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'El email o la contraseña no coinciden. Revisalos o usá «¿Olvidaste tu contraseña?».']);
        }

        if ($user->hasTwoFactorEnabled()) {
            $this->sessionLogin->holdForTwoFactor($request, $user, $request->boolean('remember'));

            return ApiResponse::ok(['two_factor_required' => true]);
        }

        $this->sessionLogin->complete($request, $user, $request->boolean('remember'));

        return CurrentUserResource::make($user)->response();
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::ok(message: 'Sesión cerrada');
    }
}
