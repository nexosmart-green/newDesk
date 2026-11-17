<?php

namespace App\Domain\StatusPage\Status;

use App\Domain\StatusPage\Models\Period;
use Carbon\CarbonImmutable;

/**
 * The 90 daily bars and the month and 90-day percentages (monitoreo portal.ComputeUptime). Only
 * down time lowers the percentage; degraded time is painted but not counted (D-039). Time before
 * $start (when monitoring began) is "no data" and left out. Overlapping periods count once.
 */
final class Uptime
{
    public const DAYS = 90;

    public const NO_DATA = 'nodata';

    /**
     * @param  iterable<Period>  $periods
     * @return array{days: list<array{date: string, status: string, down_minutes: int, degraded_minutes: int}>, month_pct: float|null, range_pct: float|null}
     */
    public static function compute(iterable $periods, CarbonImmutable $start, CarbonImmutable $now, string $timezone): array
    {
        [$down, $degraded] = [[], []];
        foreach ($periods as $period) {
            $span = [$period->started_at->getTimestamp(), ($period->ended_at ?? $now)->getTimestamp()];
            if ($period->status === ComponentState::DOWN) {
                $down[] = $span;
            } else {
                $degraded[] = $span;
            }
        }
        $down = self::merge($down);
        $degradedOnly = self::subtract(self::merge($degraded), $down);

        $from = $start->getTimestamp();
        $to = $now->getTimestamp();
        $local = $now->setTimezone($timezone);
        $first = $local->startOfDay()->subDays(self::DAYS - 1);
        $days = [];
        for ($i = 0; $i < self::DAYS; $i++) {
            $dayStart = $first->addDays($i);
            $window = [max($dayStart->getTimestamp(), $from), min($dayStart->addDay()->getTimestamp(), $to)];
            $day = ['date' => $dayStart->format('Y-m-d'), 'status' => self::NO_DATA, 'down_minutes' => 0, 'degraded_minutes' => 0];
            if ($window[1] > $window[0]) {
                $day['down_minutes'] = (int) ceil(self::overlap($down, $window) / 60);
                $day['degraded_minutes'] = (int) ceil(self::overlap($degradedOnly, $window) / 60);
                $day['status'] = match (true) {
                    $day['down_minutes'] > 0 => ComponentState::DOWN,
                    $day['degraded_minutes'] > 0 => ComponentState::DEGRADED,
                    default => ComponentState::OPERATIONAL,
                };
            }
            $days[] = $day;
        }

        return [
            'days' => $days,
            'month_pct' => self::percent($down, [max($local->startOfMonth()->getTimestamp(), $from), $to]),
            'range_pct' => self::percent($down, [max($first->getTimestamp(), $from), $to]),
        ];
    }

    /** @param  list<array{0: int, 1: int}>  $intervals @return list<array{0: int, 1: int}> */
    private static function merge(array $intervals): array
    {
        usort($intervals, fn (array $a, array $b) => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($intervals as $current) {
            $last = count($merged) - 1;
            if ($last >= 0 && $current[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $current[1]);

                continue;
            }
            $merged[] = $current;
        }

        return $merged;
    }

    /** $a minus $b (both merged). */
    private static function subtract(array $a, array $b): array
    {
        $out = [];
        foreach ($a as $current) {
            $pieces = [$current];
            foreach ($b as [$cutStart, $cutEnd]) {
                $next = [];
                foreach ($pieces as [$start, $end]) {
                    if ($cutStart >= $end || $cutEnd <= $start) {
                        $next[] = [$start, $end];

                        continue;
                    }
                    if ($cutStart > $start) {
                        $next[] = [$start, $cutStart];
                    }
                    if ($cutEnd < $end) {
                        $next[] = [$cutEnd, $end];
                    }
                }
                $pieces = $next;
            }
            $out = [...$out, ...$pieces];
        }

        return $out;
    }

    /** Seconds of the intervals inside the window. */
    private static function overlap(array $intervals, array $window): int
    {
        $total = 0;
        foreach ($intervals as [$start, $end]) {
            $total += max(0, min($end, $window[1]) - max($start, $window[0]));
        }

        return $total;
    }

    /** The share of the window without down time, floored to two decimals (99.999 shows 99.99); null for an empty window. */
    private static function percent(array $down, array $window): ?float
    {
        if ($window[1] <= $window[0]) {
            return null;
        }

        return floor(100 * (1 - self::overlap($down, $window) / ($window[1] - $window[0])) * 100) / 100;
    }
}
