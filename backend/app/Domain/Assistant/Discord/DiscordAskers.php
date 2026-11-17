<?php

namespace App\Domain\Assistant\Discord;

use App\Domain\Identity\Models\User;

/**
 * Who is behind a Discord account: the one active team user who linked it THEMSELVES (`discord_verified_at`, set
 * by /newdesk vincular). An id typed by someone else (monitoreo's admin screen) never lets the assistant act.
 * None, or ambiguous, is nobody.
 */
final class DiscordAskers
{
    public function find(?string $discordUserId): ?User
    {
        if ($discordUserId === null || $discordUserId === '') {
            return null;
        }
        $users = User::query()->where('discord_user_id', $discordUserId)->whereNotNull('discord_verified_at')
            ->active()->with(['role', 'company'])->limit(2)->get()
            ->reject(fn (User $user) => $user->isClient());

        return $users->count() === 1 ? $users->first() : null;
    }
}
