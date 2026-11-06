<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\PortalLoginCode;
use App\Domain\Identity\Models\User;
use Throwable;

/**
 * Checks a mailed portal code (RF-13.07, CA-13.15) and returns the client user it logs in, or null.
 * Every failure looks the same: unknown address, another browser, expired, spent or wrong code.
 */
final class VerifyPortalLoginCode
{
    /** @param  string|null  $nonce  the browser's portal_login cookie: the code only works where it was asked for (D-082) */
    public function __invoke(string $email, string $code, ?string $nonce): ?User
    {
        if ($nonce === null || $nonce === '') {
            return null;
        }

        $user = User::portalUserByEmail(mb_strtolower($email));
        if ($user === null) {
            return null;
        }

        // Failures from here on only happen for real accounts: they look like a wrong code (D-077).
        try {
            $stored = PortalLoginCode::query()
                ->where('user_id', $user->id)
                ->where('nonce_hash', PortalLoginCode::nonceHash($nonce))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first();

            // The guess is counted before it is compared, so parallel requests cannot try more than 5 (D-079).
            $reserved = $stored !== null && PortalLoginCode::query()
                ->whereKey($stored->id)
                ->whereNull('used_at')
                ->where('attempts', '<', PortalLoginCode::MAX_ATTEMPTS)
                ->increment('attempts') === 1;
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! $reserved || ! hash_equals($stored->code_hash, PortalLoginCode::codeHash($user->id, $code))) {
            return null;
        }

        // Another request with the same code may have won the race.
        $consumed = PortalLoginCode::query()->whereKey($stored->id)->whereNull('used_at')->update(['used_at' => now()]);
        if ($consumed !== 1) {
            return null;
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }
}
