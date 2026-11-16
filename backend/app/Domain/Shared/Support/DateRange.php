<?php

namespace App\Domain\Shared\Support;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Carbon;

/**
 * A range of local dates (Y-m-d, both days included) with a maximum length. The single place where
 * "how many days may this screen or tool ask for" is checked, for the web and MCP alike.
 */
final class DateRange
{
    private function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {}

    /** @throws BusinessRuleViolation when "Hasta" is before "Desde" or the range is longer than $maxDays */
    public static function of(string $from, string $to, int $maxDays): self
    {
        $days = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to), false) + 1;

        if ($days < 1) {
            throw new BusinessRuleViolation('«Hasta» no puede ser anterior a «Desde». Revisá las fechas.', 'invalid_range', field: 'to');
        }
        if ($days > $maxDays) {
            throw new BusinessRuleViolation(
                "Elegí un rango de hasta {$maxDays} días (pediste {$days}). Si necesitás más, consultalo por partes: un período a continuación del otro.",
                'range_too_long',
                context: ['max_days' => $maxDays],
            );
        }

        return new self($from, $to);
    }
}
