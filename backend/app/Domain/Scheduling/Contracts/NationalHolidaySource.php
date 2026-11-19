<?php

namespace App\Domain\Scheduling\Contracts;

use App\Domain\Scheduling\Exceptions\NationalHolidaysUnavailable;

/** P-26: where the national holidays (AR) of a year come from. */
interface NationalHolidaySource
{
    /**
     * The year's holidays, without the optional "días no laborables" (each company decides those).
     *
     * @return list<array{date: string, name: string}>
     *
     * @throws NationalHolidaysUnavailable
     */
    public function forYear(int $year): array;
}
