<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Carbon;

/**
 * Period of the hours reports (RF-10.01), web and MCP alike: local dates of the viewer, both days included.
 * Without dates it is the current month so far (from the 1st to today).
 */
final class ReportPeriod
{
    private function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {}

    /** @throws BusinessRuleViolation when "Hasta" ends up before "Desde" */
    public static function fromInput(User $viewer, ?string $from, ?string $to): self
    {
        $timezone = $viewer->effectiveTimezone();
        $start = filled($from) ? Carbon::parse($from, $timezone)->startOfDay() : now($timezone)->startOfMonth();
        $end = filled($to) ? Carbon::parse($to, $timezone)->startOfDay() : now($timezone)->startOfDay();

        if ($end->lt($start)) {
            throw new BusinessRuleViolation(
                "«Hasta» ({$end->format('d/m/Y')}) no puede ser anterior a «Desde» ({$start->format('d/m/Y')}). Revisá las fechas del período.",
                'invalid_range',
                field: 'to',
            );
        }

        return new self($start, $end);
    }
}
