<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\ConfirmTwoFactor;
use App\Domain\Identity\Actions\DisableTwoFactor;
use App\Domain\Identity\Actions\StartTwoFactorSetup;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorSetupController extends Controller
{
    public function store(Request $request, StartTwoFactorSetup $startSetup): JsonResponse
    {
        return ApiResponse::ok($startSetup($request->user()));
    }

    public function confirm(Request $request, ConfirmTwoFactor $confirm): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        return ApiResponse::ok(
            ['recovery_codes' => $confirm($request->user(), $request->string('code'))],
            'Verificación en dos pasos activada. Guardá los códigos de recuperación: no se vuelven a mostrar.',
        );
    }

    public function destroy(Request $request, DisableTwoFactor $disable): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password:web']]);
        $disable($request->user());

        return ApiResponse::ok(message: 'Verificación en dos pasos desactivada.');
    }
}
