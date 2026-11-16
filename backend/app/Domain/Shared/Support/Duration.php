<?php

namespace App\Domain\Shared\Support;

/**
 * R-5: durations are integer seconds everywhere; this is the one place that renders them for people.
 */
final class Duration
{
    /** `5400` → `1h 30m` (negative corrections keep their sign: `-0h 30m`). */
    public static function humanize(int $seconds): string
    {
        $sign = $seconds < 0 ? '-' : '';
        $seconds = abs($seconds);

        return $sign.intdiv($seconds, 3600).'h '.str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT).'m';
    }
}
