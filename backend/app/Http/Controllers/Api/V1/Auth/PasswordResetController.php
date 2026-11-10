<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Only active users can reset; the answer is identical either way (no account enumeration).
        Password::broker()->sendResetLink(['email' => $request->string('email'), 'status' => 'active', 'client_id' => null]);

        return ApiResponse::ok(message: 'Si el email está registrado, te enviamos un enlace para restablecer la contraseña.');
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker()->reset(
            [...$request->only('email', 'password', 'password_confirmation', 'token'), 'status' => 'active', 'client_id' => null],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->setRememberToken(null);
                $user->save();
                // A reset after a compromise must also kill MCP tokens the attacker may have created.
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o venció. Pedí uno nuevo.']);
        }

        return ApiResponse::ok(message: 'Contraseña actualizada. Ya podés ingresar.');
    }
}
