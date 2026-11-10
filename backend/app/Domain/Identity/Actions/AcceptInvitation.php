<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserInvitation;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

final class AcceptInvitation
{
    public function __invoke(string $token, string $password): User
    {
        return DB::transaction(function () use ($token, $password) {
            $invitation = UserInvitation::query()->withoutGlobalScopes()
                ->where('token_hash', UserInvitation::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || ! $invitation->isUsable()) {
                throw new BusinessRuleViolation(
                    'La invitación no es válida o venció. Pedile al administrador que te la reenvíe.',
                    'invitation_invalid',
                );
            }

            $user = $invitation->user;
            if ($user->status !== UserStatus::Invited) {
                throw new BusinessRuleViolation('Esta invitación ya no está disponible.', 'invitation_invalid');
            }

            $user->forceFill([
                'password' => $password,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ])->save();

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }
}
