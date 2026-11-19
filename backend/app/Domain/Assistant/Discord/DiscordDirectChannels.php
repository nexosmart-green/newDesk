<?php

namespace App\Domain\Assistant\Discord;

use Illuminate\Contracts\Cache\Repository as Cache;

/** Where the answer to a DM goes: the DM channel it was asked in, kept until the turn ends (RF-14.08 phase 5). */
final class DiscordDirectChannels
{
    private const TTL_HOURS = 2;

    public function __construct(private readonly Cache $cache) {}

    public function remember(int $answerId, string $channelId): void
    {
        $this->cache->put(self::key($answerId), $channelId, now()->addHours(self::TTL_HOURS));
    }

    public function pull(int $answerId): ?string
    {
        $channelId = $this->cache->pull(self::key($answerId));

        return is_string($channelId) ? $channelId : null;
    }

    private static function key(int $answerId): string
    {
        return "assistant:discord-dm:{$answerId}";
    }
}
