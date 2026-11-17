<?php

namespace App\Domain\Assistant\Discord;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * One-time codes that tie a Discord account to a newDesk user (`/newdesk vincular codigo:…`). Kept hashed in the
 * cache for 10 minutes; used once.
 */
final class DiscordLinkCodes
{
    public const TTL_MINUTES = 10;

    /** Without look-alikes (0/O, 1/I/L): read from one screen, typed in another. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly Cache $cache) {}

    public function issue(int $userId): string
    {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        $this->cache->put(self::key($code), $userId, now()->addMinutes(self::TTL_MINUTES));

        return $code;
    }

    /** The user the code belongs to, consuming it once even if two accounts race for it; null when unknown, used or expired. */
    public function redeem(string $code): ?int
    {
        $key = self::key($code);
        $lock = $this->cache->lock($key.':redeem', 5);
        if (! $lock->get()) {
            return null;
        }

        try {
            $userId = $this->cache->pull($key);
        } finally {
            $lock->release();
        }

        return is_int($userId) ? $userId : null;
    }

    private static function key(string $code): string
    {
        return 'assistant:discord-link:'.hash('sha256', Str::upper(trim($code)));
    }
}
