<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Discord\DiscordLinkCodes;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Actions\SetDiscordUserId;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;

/**
 * RF-14.08: `/newdesk vincular codigo:…`. Ties the Discord account to the user who generated the code, on the same
 * `discord_user_id` monitoreo uses (recorded in the activity, like when an Admin sets it), and marks it verified:
 * only a link made this way lets the assistant act for the person. One Discord account, one newDesk user.
 */
final class LinkDiscordAccount
{
    public function __construct(private readonly DiscordLinkCodes $codes, private readonly SetDiscordUserId $setDiscordUserId) {}

    public function __invoke(string $code, string $discordUserId): User
    {
        $userId = $this->codes->redeem($code);
        $user = $userId === null ? null : User::query()->active()->find($userId);
        if ($user === null) {
            throw new BusinessRuleViolation('Ese código no sirve: venció o ya se usó. Generá otro en newDesk → Asistente → Conectar Discord.', 'discord_link_invalid');
        }

        // In any company: the same account in two would leave both ambiguous.
        $taken = User::query()->where('discord_user_id', $discordUserId)->whereKeyNot($user->id)->active()->exists();
        if ($taken) {
            throw new BusinessRuleViolation('Esa cuenta de Discord ya está vinculada a otra persona de newDesk. Pedile a un administrador que la desvincule.', 'discord_link_taken');
        }

        $user = ($this->setDiscordUserId)($user, $discordUserId, $user);
        $user->forceFill(['discord_verified_at' => now()])->save();

        return $user;
    }
}
