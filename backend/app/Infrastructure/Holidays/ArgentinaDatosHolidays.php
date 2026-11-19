<?php

namespace App\Infrastructure\Holidays;

use App\Domain\Scheduling\Contracts\NationalHolidaySource;
use App\Domain\Scheduling\Exceptions\NationalHolidaysUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * P-26: the public ArgentinaDatos API (`/v1/feriados/{año}`), which follows each year's decree for the movable ones.
 * "inamovible" and "trasladable" are holidays; "puente" days are optional days off, left to each company.
 * Its answer is external data: only well-formed dates of the asked year, names clipped to the column.
 */
final class ArgentinaDatosHolidays implements NationalHolidaySource
{
    private const HOLIDAY_TYPES = ['inamovible', 'trasladable'];

    public function forYear(int $year): array
    {
        try {
            $rows = Http::acceptJson()->timeout(10)->retry(2, 500, throw: true)
                ->get(rtrim((string) config('newdesk.holidays.source_url'), '/')."/{$year}")->throw()->json();
        } catch (ConnectionException|RequestException) {
            throw NationalHolidaysUnavailable::make();
        }
        if (! is_array($rows)) {
            throw NationalHolidaysUnavailable::make();
        }

        $holidays = [];
        foreach ($rows as $row) {
            $date = is_array($row) ? ($row['fecha'] ?? null) : null;
            $name = is_array($row) ? trim((string) ($row['nombre'] ?? '')) : '';
            if (! is_string($date) || ! preg_match("/^{$year}-\d{2}-\d{2}$/", $date) || ! checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), $year)
                || $name === '' || ! in_array($row['tipo'] ?? null, self::HOLIDAY_TYPES, true)) {
                continue;
            }
            $holidays[] = ['date' => $date, 'name' => Str::limit($name, 117)];
        }

        return $holidays;
    }
}
