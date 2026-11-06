<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\TwoFactor\TwoFactorAuthenticator;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * Step 2: a valid code from the authenticator app activates 2FA and returns the recovery codes (shown once).
 */
final class ConfirmTwoFactor
{
    public function __construct(
        private readonly TwoFactorAuthenticator $authenticator,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @return list<string> recovery codes */
    public function __invoke(User $user, string $code): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new BusinessRuleViolation('Ya tenés la verificación en dos pasos activa.', 'two_factor_already_enabled');
        }

        if ($user->two_factor_secret === null || ! $this->authenticator->verifyCode($user->two_factor_secret, $code, $user->id)) {
            throw new BusinessRuleViolation('El código no es válido. Revisá la hora del celular y probá de nuevo.', 'two_factor_invalid_code');
        }

        $recoveryCodes = $this->authenticator->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        ($this->recordActivity)('user.two_factor_enabled', $user, $user);

        return $recoveryCodes;
    }
}
