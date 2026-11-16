<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;

/**
 * RF-05.09: a manual stretch is typed as a local date plus "Desde"/"Hasta" (HH:MM) in the person's timezone
 * and stored in UTC. Web and MCP convert it here, so both read the same hours the same way.
 */
final class LocalTimeRange
{
    /** @return array{0: Carbon, 1: Carbon} [startedAt, endedAt] in UTC */
    public static function toUtc(User $user, string $date, string $from, string $to): array
    {
        $timezone = $user->effectiveTimezone();

        return [
            Carbon::createFromFormat('Y-m-d H:i', "{$date} {$from}", $timezone)->utc(),
            Carbon::createFromFormat('Y-m-d H:i', "{$date} {$to}", $timezone)->utc(),
        ];
    }
}
