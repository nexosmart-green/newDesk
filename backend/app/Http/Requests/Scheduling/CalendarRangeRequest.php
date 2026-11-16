<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Scheduling\Queries\Calendar;
use App\Http\Requests\LocalDateRangeRequest;

/** "Mi calendario" and the shift grid. */
class CalendarRangeRequest extends LocalDateRangeRequest
{
    protected function maxDays(): int
    {
        return Calendar::MAX_DAYS;
    }
}
