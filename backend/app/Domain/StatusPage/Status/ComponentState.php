<?php

namespace App\Domain\StatusPage\Status;

use Carbon\CarbonImmutable;

/**
 * What the monitor learned of a component on its last run (monitoreo store.ComponentState), and
 * how one more result changes it (portal.Next).
 */
final class ComponentState
{
    public const OPERATIONAL = 'operational';

    public const DEGRADED = 'degraded';

    public const DOWN = 'down';

    public const UNKNOWN = 'unknown';

    /**
     * Bad results must last this long in a row before a component shows degraded: the link from
     * Servidor 5 to Cloudflare drops packets for 2-4 min every ~25 min (D-075, D-121).
     */
    public const DEGRADED_AFTER = 600;

    /**
     * Down needs this long of down results in a row, counted from the first down one: a shorter cut
     * leaves no period, incident nor back mail (D-071, D-075, CA-13.12).
     */
    public const DOWN_AFTER = 600;

    /** "The worst wins"; unknown never beats a real status. */
    private const RANK = [self::UNKNOWN => 0, self::OPERATIONAL => 1, self::DEGRADED => 2, self::DOWN => 3];

    public const LABELS = [self::OPERATIONAL => 'Operativo', self::DEGRADED => 'Degradado', self::DOWN => 'Caído', self::UNKNOWN => 'Sin datos'];

    public function __construct(
        public string $status = self::UNKNOWN,
        public ?CarbonImmutable $statusSince = null,
        public string $phrase = '',
        public string $detail = '',
        public int $badStreak = 0,
        public ?CarbonImmutable $badSince = null,
        public ?CarbonImmutable $downSince = null,
        public ?CarbonImmutable $lastCheckedAt = null,
        public ?CarbonImmutable $lastOkAt = null,
        public ?int $latencyMs = null,
        public ?int $statusCode = null,
        public ?CarbonImmutable $sslExpiresAt = null,
    ) {}

    /** The most severe status; unknown only when every one is unknown. */
    public static function worst(string ...$statuses): string
    {
        $worst = self::UNKNOWN;
        foreach ($statuses as $status) {
            if (self::RANK[$status] > self::RANK[$worst]) {
                $worst = $status;
            }
        }

        return $worst;
    }

    public static function isBad(string $status): bool
    {
        return $status === self::DEGRADED || $status === self::DOWN;
    }

    /**
     * Applies one result. A bad one changes the status only after DEGRADED_AFTER of bad results in
     * a row, and down also needs DOWN_AFTER of down results; a good or unknown one applies at once.
     * The change is null unless the status changed: then it closes the open period and, for a bad
     * status, opens one from the first failure (not from the confirmation).
     *
     * @return array{0: self, 1: array{at: CarbonImmutable, open: string, started_at: CarbonImmutable|null, detail: string}|null}
     */
    public function next(Result $result, CarbonImmutable $now): array
    {
        $next = clone $this;
        $next->lastCheckedAt = $now;
        $next->latencyMs = $result->latencyMs;
        $next->statusCode = $result->statusCode;
        $next->sslExpiresAt = $result->sslExpiresAt ?? $next->sslExpiresAt;
        $next->lastOkAt = $result->lastOkAt ?? $next->lastOkAt;
        $next->detail = $result->detail;

        $target = $result->status;
        $next->downSince = $result->status !== self::DOWN ? null : ($next->downSince ?? $now);
        if (self::isBad($result->status)) {
            $next->badStreak++;
            $next->badSince ??= $now;
            if ($result->status === self::DOWN && $this->status !== self::DOWN && self::since($next->downSince, $now) < self::DOWN_AFTER) {
                $target = $this->status; // a short cut never shows (D-071)
            } elseif (! self::isBad($this->status) && self::since($next->badSince, $now) < self::DEGRADED_AFTER) {
                $target = $this->status; // a short problem never shows (D-121)
            }
        } else {
            $next->badStreak = 0;
            $next->badSince = null;
        }
        if ($target === self::OPERATIONAL && $result->status === self::OPERATIONAL && $result->lastOkAt === null) {
            $next->lastOkAt = $now;
        }

        if ($target === $this->status && $this->statusSince !== null) {
            if ($target === $result->status) {
                $next->phrase = $result->phrase;
            }

            return [$next, null];
        }
        $next->status = $target;
        $next->phrase = $target !== $result->status ? $this->phrase : $result->phrase;
        $since = self::isBad($target) && ! self::isBad($this->status) && $next->badSince !== null ? $next->badSince : $now;
        $next->statusSince = $since;
        $bad = self::isBad($target);

        return [$next, ['at' => $now, 'open' => $bad ? $target : '', 'started_at' => $bad ? $since : null, 'detail' => $bad ? $result->detail : '']];
    }

    private static function since(CarbonImmutable $start, CarbonImmutable $now): int
    {
        return $now->getTimestamp() - $start->getTimestamp();
    }
}
