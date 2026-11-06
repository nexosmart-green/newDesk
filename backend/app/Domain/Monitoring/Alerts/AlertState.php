<?php

namespace App\Domain\Monitoring\Alerts;

use Illuminate\Support\Facades\Cache;

/**
 * What the alert rules remember between polls (ADR-0002 §3): streaks, PM2 restart counters,
 * failed-login samples and the units each server's timers start. It lives in the cache (Redis in
 * production) with a TTL, so any process can evaluate rules; every key lives here.
 */
final class AlertState
{
    /** Streaks and PM2 counters: longer than any "for" (5 min) and the PM2 window (15 min). */
    private const STATE_TTL = 900;

    /** Failed-login samples span 65 min; the timers outlive a few failed cron polls (10 min apart). */
    private const HISTORY_TTL = 3900;

    /** @return array<string, array{since: int, count: int}> target => streak */
    public function streaks(int $serverId, string $rule): array
    {
        return Cache::get(self::key($serverId, "streaks:{$rule}"), []);
    }

    /** @param  array<string, array{since: int, count: int}>  $streaks */
    public function saveStreaks(int $serverId, string $rule, array $streaks): void
    {
        $this->save(self::key($serverId, "streaks:{$rule}"), $streaks, self::STATE_TTL);
    }

    /** @return array<string, array{restarts: int, increased_at: int|null}> target => counter */
    public function pm2Restarts(int $serverId): array
    {
        return Cache::get(self::key($serverId, 'pm2'), []);
    }

    /** @param  array<string, array{restarts: int, increased_at: int|null}>  $counters */
    public function savePm2Restarts(int $serverId, array $counters): void
    {
        $this->save(self::key($serverId, 'pm2'), $counters, self::STATE_TTL);
    }

    /** @return list<array{at: int, total: int, basis: string}> */
    public function loginHistory(int $serverId): array
    {
        return Cache::get(self::key($serverId, 'logins'), []);
    }

    /** @param  list<array{at: int, total: int, basis: string}>  $samples */
    public function saveLoginHistory(int $serverId, array $samples): void
    {
        $this->save(self::key($serverId, 'logins'), $samples, self::HISTORY_TTL);
    }

    /** @return array<string, true> unit => true ("db-backup.service") */
    public function timerServices(int $serverId): array
    {
        return Cache::get(self::key($serverId, 'timers'), []);
    }

    /** @param  array<string, true>  $units */
    public function saveTimerServices(int $serverId, array $units): void
    {
        $this->save(self::key($serverId, 'timers'), $units, self::HISTORY_TTL);
    }

    /** A deleted server leaves nothing behind (monitoreo Engine.ForgetServer). */
    public function forgetServer(int $serverId): void
    {
        foreach (AlertRules::RULES as $rule) {
            Cache::forget(self::key($serverId, "streaks:{$rule}"));
        }
        foreach (['pm2', 'logins', 'timers'] as $name) {
            Cache::forget(self::key($serverId, $name));
        }
    }

    /** Applying one rule of one server is serialized across processes. */
    public static function lockKey(int $serverId, string $rule): string
    {
        return self::key($serverId, "lock:{$rule}");
    }

    private function save(string $key, array $value, int $ttl): void
    {
        $value === [] ? Cache::forget($key) : Cache::put($key, $value, $ttl);
    }

    private static function key(int $serverId, string $name): string
    {
        return "monitoring:alerts:{$serverId}:{$name}";
    }
}
