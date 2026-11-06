<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\TwoFactor\TwoFactorAuthenticator;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * Step 1: stores an unconfirmed secret. 2FA is not active until ConfirmTwoFactor succeeds.
 */
final class StartTwoFactorSetup
{
    public function __construct(private readonly TwoFactorAuthenticator $authenticator) {}

    /** @return array{secret: string, otpauth_url: string} */
    public function __invoke(User $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new BusinessRuleViolation('Ya tenés la verificación en dos pasos activa.', 'two_factor_already_enabled');
        }

        $secret = $this->authenticator->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => null])->save();

        return ['secret' => $secret, 'otpauth_url' => $this->authenticator->otpAuthUrl($user, $secret)];
    }
}
