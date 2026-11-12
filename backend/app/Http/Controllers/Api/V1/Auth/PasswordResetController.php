<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\ResetPassword;
use App\Domain\Identity\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        // Only active users can reset; the answer is identical either way (no account enumeration).
        Password::broker()->sendResetLink(['email' => $request->string('email')->value(), 'status' => UserStatus::Active->value]);

        return ApiResponse::ok(message: 'Si el email está registrado, te va a llegar un enlace para restablecer la contraseña. Revisá también la carpeta de spam.');
    }

    public function reset(ResetPasswordRequest $request, ResetPassword $resetPassword): JsonResponse
    {
        $resetPassword($request->string('email')->value(), $request->string('token')->value(), $request->string('password')->value());

        return ApiResponse::ok(message: 'Contraseña actualizada. Ya podés ingresar.');
    }
}
