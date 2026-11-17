<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\PortalLoginCode;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\PortalLoginCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Timebox;
use Throwable;

/**
 * Mails a portal login code (RF-13.07, CA-13.15). Every address gets the same answer in about the
 * same time (D-077): only an active client user with exactly that address gets a code (D-076), at
 * most 5 per hour from any IP (D-081, D-087) unless the browser already entered the account (D-114).
 */
final class RequestPortalLoginCode
{
    /** Hides behind a constant response time whether the address exists. */
    private const MIN_MICROSECONDS = 400_000;

    public function __construct(private readonly Timebox $timebox) {}

    /** @param  string  $nonce  the requesting browser's portal_login cookie (D-082) */
    public function __invoke(string $email, string $nonce, string $ip, ?string $knownBrowser): void
    {
        $this->timebox->call(function () use ($email, $nonce, $ip, $knownBrowser) {
            $user = User::portalUserByEmail(mb_strtolower($email));
            if ($user === null) {
                return;
            }

            // Failures from here on only happen for real accounts: they answer like an unknown address (D-077).
            try {
                $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
                if ($this->issue($user, $code, $nonce, $ip, self::isKnownBrowser($knownBrowser, $user))) {
                    $user->notify(new PortalLoginCodeNotification($code));
                }
            } catch (Throwable $e) {
                report($e);
            }
        }, self::MIN_MICROSECONDS);
    }

    /** Marks a browser that entered the account. Not a credential: it only skips the shared budget (D-114). */
    public static function knownBrowserToken(User $user): string
    {
        return $user->id.'.'.PortalLoginCode::digest('portal-known-browser', (string) $user->id);
    }

    private static function isKnownBrowser(?string $token, User $user): bool
    {
        return $token !== null && hash_equals(self::knownBrowserToken($user), $token);
    }

    private function issue(User $user, string $code, string $nonce, string $ip, bool $knownBrowser): bool
    {
        return DB::transaction(function () use ($user, $code, $nonce, $ip, $knownBrowser) {
            // The account's row is locked while counting: parallel requests cannot all fit under the budget.
            User::query()->whereKey($user->id)->lockForUpdate()->value('id');

            $issuedLastHour = PortalLoginCode::query()
                ->where('user_id', $user->id)
                ->where('created_at', '>', now()->subHour())
                ->count();
            if (! $knownBrowser && $issuedLastHour >= PortalLoginCode::PER_ACCOUNT_HOURLY) {
                Log::warning('Portal login code budget used up', ['user_id' => $user->id, 'ip' => $ip]);

                return false;
            }

            $nonceHash = PortalLoginCode::nonceHash($nonce);
            // Only this browser's previous code dies: a stranger cannot kill the one the owner got (D-082).
            PortalLoginCode::query()
                ->where('user_id', $user->id)
                ->where('nonce_hash', $nonceHash)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            PortalLoginCode::query()->create([
                'user_id' => $user->id,
                'code_hash' => PortalLoginCode::codeHash($user->id, $code),
                'nonce_hash' => $nonceHash,
                'ip' => $ip,
                'expires_at' => now()->addMinutes(PortalLoginCode::TTL_MINUTES),
            ]);

            return true;
        });
    }
}
