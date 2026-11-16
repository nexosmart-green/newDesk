<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\TimeTracking\Models\TimeEntry;

/**
 * "Historial de horas" as CSV for Excel in Argentina: UTF-8 with BOM, `;` separator, decimal comma,
 * local dates and times of whoever exports.
 */
final class TimeEntryCsv
{
    public const HEADERS = ['Fecha', 'Inicio', 'Fin', 'Duración', 'Horas', 'Persona', 'Proyecto', 'Tarea', 'Título', 'Comentario', 'Origen'];

    private const BOM = "\xEF\xBB\xBF";

    /**
     * @param  resource  $handle
     * @param  iterable<TimeEntry>  $entries  with user, task and project loaded
     */
    public function write($handle, iterable $entries, string $timezone): void
    {
        fwrite($handle, self::BOM);
        $this->put($handle, self::HEADERS);

        foreach ($entries as $entry) {
            $this->put($handle, $this->row($entry, $timezone));
        }
    }

    /** @return list<string> */
    public function row(TimeEntry $entry, string $timezone): array
    {
        $startedAt = $entry->started_at->copy()->setTimezone($timezone);
        $seconds = $entry->elapsedSeconds();

        return [
            $startedAt->format('d/m/Y'),
            $startedAt->format('H:i'),
            $entry->ended_at?->copy()->setTimezone($timezone)->format('H:i') ?? '',
            self::hoursAndMinutes($seconds),
            number_format($seconds / 3600, 2, ',', ''),
            self::text($entry->user->fullName()),
            self::text($entry->project->name),
            self::text($entry->task->code),
            self::text($entry->task->title),
            self::text((string) $entry->comment),
            $entry->source->label(),
        ];
    }

    public static function hoursAndMinutes(int $seconds): string
    {
        return intdiv($seconds, 3600).':'.str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT);
    }

    /** Spreadsheets run cells starting with = + - @ as formulas: neutralize them (CSV injection). */
    private static function text(string $value): string
    {
        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'{$value}" : $value;
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $fields
     */
    private function put($handle, array $fields): void
    {
        fputcsv($handle, $fields, ';', '"', '');
    }
}
