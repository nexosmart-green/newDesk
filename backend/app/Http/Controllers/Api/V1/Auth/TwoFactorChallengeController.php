<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\TwoFactor\TwoFactorAuthenticator;
use App\Http\Auth\SessionLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Resources\CurrentUserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Second login step for users with 2FA enabled.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly SessionLogin $sessionLogin,
        private readonly TwoFactorAuthenticator $authenticator,
    ) {}

    public function store(TwoFactorChallengeRequest $request): JsonResponse
    {
        $pending = $this->sessionLogin->pendingTwoFactor($request);
        if ($pending === null) {
            return ApiResponse::error('La sesión de ingreso venció. Volvé a ingresar tu contraseña.', 401);
        }

        if (! $this->authenticator->verify($pending['user'], $request->input('code'), $request->input('recovery_code'))) {
            if (! $this->sessionLogin->registerFailedCode($request)) {
                return ApiResponse::error('Demasiados códigos incorrectos. Volvé a ingresar tu contraseña.', 401);
            }
            throw ValidationException::withMessages(['code' => 'El código no es válido.']);
        }

        $this->sessionLogin->complete($request, $pending['user'], $pending['remember']);

        return CurrentUserResource::make($pending['user'])->response();
    }
}
