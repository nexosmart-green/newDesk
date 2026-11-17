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
            $user->forceFill(['discord_user_id' => $discordUserId])->save();
            ($this->recordActivity)('user.discord_id_changed', $user, $actor, ['discord_user_id' => $before], ['discord_user_id' => $discordUserId]);

            return $user;
        });
    }
}
