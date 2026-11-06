<?php

namespace App\Http\Auth;

use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cookie-session login steps shared by the password step and the 2FA challenge.
 */
final class SessionLogin
{
    private const PENDING_KEY = 'login.pending';

    private const PENDING_TTL_SECONDS = 300;

    public function findActiveUserByCredentials(string $email, string $password): ?User
    {
        /** @var UserProvider $provider */
        $provider = Auth::guard('web')->getProvider();
        $user = $provider->retrieveByCredentials(['email' => $email]);

        if (! $user instanceof User || ! $user->isActive() || ! $provider->validateCredentials($user, ['password' => $password])) {
            return null;
        }

        return $user;
    }

    public function holdForTwoFactor(Request $request, User $user, bool $remember): void
    {
        $request->session()->put(self::PENDING_KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
            'expires_at' => now()->addSeconds(self::PENDING_TTL_SECONDS)->getTimestamp(),
        ]);
    }

    /** @return array{user: User, remember: bool}|null */
    public function pendingTwoFactor(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_KEY);
        if (! is_array($pending) || $pending['expires_at'] < now()->getTimestamp()) {
            return null;
        }

        $user = User::query()->find($pending['user_id']);

        return $user !== null && $user->isActive() ? ['user' => $user, 'remember' => (bool) $pending['remember']] : null;
    }

    public function complete(Request $request, User $user, bool $remember): void
    {
        $request->session()->forget(self::PENDING_KEY);
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
    }
}
