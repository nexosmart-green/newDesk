<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Queries\Timesheet;
use App\Http\Requests\LocalDateRangeRequest;

/** RF-05.12 "Mi semana". */
class TimesheetRequest extends LocalDateRangeRequest
{
    protected function maxDays(): int
    {
        return Timesheet::MAX_DAYS;
    }
}
