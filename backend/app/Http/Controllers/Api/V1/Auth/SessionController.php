<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Models\User;
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
    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        // Only active users can log in (RF-01.10); invited users have no password yet.
        if (! Auth::guard('web')->attempt([...$credentials, 'status' => 'active'], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $user->forceFill(['last_login_at' => now()])->save();

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
