<?php

namespace App\Domain\Assistant\Discord;

use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Identity\Models\User;

/** RF-14.08: a Discord question within two hours of the last one continues that conversation (and its Claude Code session). */
final class DiscordConversations
{
    private const HOURS = 2;

    public function current(User $user): ?AssistantConversation
    {
        return AssistantConversation::query()->forCompany($user->company_id)->where('user_id', $user->id)
            ->where('channel', AssistantChannel::Discord)->where('last_message_at', '>=', now()->subHours(self::HOURS))
            ->latest('last_message_at')->first();
    }
}
