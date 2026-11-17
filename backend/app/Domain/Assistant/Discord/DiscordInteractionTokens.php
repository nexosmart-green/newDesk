<?php

namespace App\Domain\Assistant\Discord;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\Encrypter;

/**
 * The interaction token of a pending /newdesk answer: it lets anyone edit that Discord message for 15 minutes,
 * so it lives only encrypted in the cache, for a bit less than that, and never in the database or the logs.
 */
final class DiscordInteractionTokens
{
    private const TTL_MINUTES = 14;

    public function __construct(private readonly Cache $cache, private readonly Encrypter $encrypter) {}

    public function remember(int $answerId, string $token): void
    {
        $this->cache->put(self::key($answerId), $this->encrypter->encryptString($token), now()->addMinutes(self::TTL_MINUTES));
    }

    public function pull(int $answerId): ?string
    {
        $encrypted = $this->cache->pull(self::key($answerId));

        return is_string($encrypted) ? $this->encrypter->decryptString($encrypted) : null;
    }

    private static function key(int $answerId): string
    {
        return "assistant:discord-token:{$answerId}";
    }
}
