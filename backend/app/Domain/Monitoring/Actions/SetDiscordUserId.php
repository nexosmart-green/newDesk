<?php

namespace App\Domain\Monitoring\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * A team member's Discord id (monitoreo users.discord_user_id, D-054): who the notices of the
 * projects they lead ping. Null clears it: the notices then name them by mail.
 */
final class SetDiscordUserId
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $user, ?string $discordUserId, User $actor): User
    {
        return DB::transaction(function () use ($user, $discordUserId, $actor) {
            $before = $user->discord_user_id;
            // A changed id is no longer the one the person verified for the assistant (RF-14.08).
            $user->forceFill(['discord_user_id' => $discordUserId, ...($before !== $discordUserId ? ['discord_verified_at' => null] : [])])->save();
            ($this->recordActivity)('user.discord_id_changed', $user, $actor, ['discord_user_id' => $before], ['discord_user_id' => $discordUserId]);

            return $user;
        });
    }
}
