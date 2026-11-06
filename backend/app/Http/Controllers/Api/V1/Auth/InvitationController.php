<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\AcceptInvitation;
use App\Domain\Identity\Models\UserInvitation;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Public endpoints reached from the invitation e-mail (RF-01.01).
 */
class InvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invitation = UserInvitation::query()->withoutGlobalScopes()
            ->with('user.company')
            ->where('token_hash', UserInvitation::hashToken($token))
            ->first();

        if ($invitation === null || ! $invitation->isUsable()) {
            return ApiResponse::error('La invitación no es válida o venció. Pedile al administrador que te la reenvíe.', 404);
        }

        return ApiResponse::ok([
            'first_name' => $invitation->user->first_name,
            'email' => $invitation->user->email,
            'company' => $invitation->user->company->name,
        ]);
    }

    public function accept(Request $request, string $token, AcceptInvitation $accept): JsonResponse
    {
        $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);

        $user = $accept($token, $request->string('password'));

        return ApiResponse::ok(['email' => $user->email], 'Listo. Ya podés ingresar con tu email y contraseña.');
    }
}
