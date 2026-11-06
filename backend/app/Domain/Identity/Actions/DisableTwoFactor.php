<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

final class DisableTwoFactor
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $user): void
    {
        if ($user->requiresTwoFactor()) {
            throw new BusinessRuleViolation(
                'La verificación en dos pasos es obligatoria para administradores.',
                'two_factor_required',
            );
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        ($this->recordActivity)('user.two_factor_disabled', $user, $user);
    }
}
