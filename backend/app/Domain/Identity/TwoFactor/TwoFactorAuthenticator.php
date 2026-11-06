<?php

namespace App\Domain\Identity\TwoFactor;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) verification + single-use recovery codes.
 */
final class TwoFactorAuthenticator
{
    private const RECOVERY_CODES = 8;

    public function __construct(private readonly Google2FA $engine) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function otpAuthUrl(User $user, string $secret): string
    {
        return $this->engine->getQRCodeUrl($user->company->name.' newDesk', $user->email, $secret);
    }

    /** @return list<string> */
    public function generateRecoveryCodes(): array
    {
        return array_map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)), range(1, self::RECOVERY_CODES));
    }

    public function verifyCode(string $secret, string $code, int $userId): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        // A code accepted once cannot be replayed within its validity window.
        $replayKey = "2fa-used:{$userId}:{$code}";
        if (Cache::has($replayKey) || ! $this->engine->verifyKey($secret, $code, 1)) {
            return false;
        }
        Cache::put($replayKey, true, now()->addMinutes(2));

        return true;
    }

    /** Verifies a TOTP code or consumes a recovery code. */
    public function verify(User $user, ?string $code, ?string $recoveryCode): bool
    {
        $secret = $user->two_factor_secret;
        if ($secret === null || $user->two_factor_confirmed_at === null) {
            return false;
        }

        if ($code !== null && $code !== '') {
            return $this->verifyCode($secret, $code, $user->id);
        }

        if ($recoveryCode === null || $recoveryCode === '') {
            return false;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search(Str::lower(trim($recoveryCode)), $codes, true);
        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }
}
