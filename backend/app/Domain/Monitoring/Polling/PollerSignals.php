<?php

namespace App\Domain\Monitoring\Polling;

use Illuminate\Support\Facades\Cache;

/**
 * What web requests tell the long-running poller, and the poller's sign of life,
 * through the cache (Redis in production): every key lives here.
 */
final class PollerSignals
{
    private const RELOAD = 'monitoring:poller:reload';

    private const TICK = 'monitoring:poller:tick';

    /** The browser beats every few seconds; the live mode ends this long after the last one (CA-13.06). */
    public const LIVE_TTL = 30;

    /** /health reports the poller dead past this (monitoreo Poller.Healthy). */
    public const TICK_STALE_AFTER = 30;

    /** Servers changed: re-read them now instead of at the next 15 s reload. */
    public function requestReload(): void
    {
        // A counter, not a flag: the poller never deletes it, so no request can be lost in between.
        Cache::increment(self::RELOAD);
    }

    /** "Forzar actualización" of one section, or of everything when null. */
    public function requestRefresh(int $serverId, ?string $section): void
    {
        $key = self::refreshKey($serverId);
        $sections = Cache::get($key, []);
        $sections[] = $section ?? '';
        Cache::put($key, array_values(array_unique($sections)), 300);
    }

    /** Someone has the server's detail open. */
    public function beat(int $serverId): void
    {
        Cache::put(self::liveKey($serverId), true, self::LIVE_TTL);
    }

    public function markTick(int $now): void
    {
        Cache::put(self::TICK, $now, 3600);
    }

    public function pollerAlive(int $now): bool
    {
        $tick = Cache::get(self::TICK); // Redis hands numbers back as strings

        return is_numeric($tick) && $now - (int) $tick < self::TICK_STALE_AFTER;
    }

    /**
     * Read by the poller every tick. Refresh requests are consumed; `reload` is the counter's
     * current value, to compare with the one seen before.
     *
     * @param  list<int>  $serverIds
     * @return array{reload: int, refresh: array<int, list<string|null>>, live: list<int>}
     */
    public function take(array $serverIds): array
    {
        $keys = [self::RELOAD];
        foreach ($serverIds as $id) {
            $keys[] = self::refreshKey($id);
            $keys[] = self::liveKey($id);
        }
        $values = Cache::many($keys);

        $refresh = [];
        $live = [];
        foreach ($serverIds as $id) {
            if (is_array($sections = $values[self::refreshKey($id)] ?? null)) {
                $refresh[$id] = array_map(fn (string $section) => $section === '' ? null : $section, $sections);
                Cache::forget(self::refreshKey($id));
            }
            if (($values[self::liveKey($id)] ?? null) === true) {
                $live[] = $id;
            }
        }

        return ['reload' => (int) ($values[self::RELOAD] ?? 0), 'refresh' => $refresh, 'live' => $live];
    }

    private static function refreshKey(int $serverId): string
    {
        return "monitoring:refresh:{$serverId}";
    }

    private static function liveKey(int $serverId): string
    {
        return "monitoring:live:{$serverId}";
    }
}
