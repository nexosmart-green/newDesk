<?php

namespace App\Domain\Assistant\Support;

use App\Domain\Shared\Support\Duration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * RF-14.06: the weekly summary in a fixed, short markdown text. It always works (no AI, no cap): it is what goes
 * out when the assistant is off, over its monthly cap or fails, and the reference the AI version is checked against.
 * Names and titles come from people: they are flattened to plain text (no line breaks, links, mentions or markdown).
 */
final class WeeklySummaryTemplate
{
    private const TOP = 5;

    private const NAME_MAX = 60;

    /** @param  array<string, mixed>  $data  WeeklySummaryData */
    public static function render(array $data): string
    {
        $from = Carbon::parse($data['week']['from']);
        $to = Carbon::parse($data['week']['to']);
        $sections = [
            "**Resumen de la semana del {$from->format('d/m')} al {$to->format('d/m')}** (".self::clean($data['company']).')',
            '**Bolsas por agotarse:** '.($data['bags_running_out'] === [] ? 'ninguna.'
                : self::list($data['bags_running_out'], fn (array $row) => self::project($row)." ({$row['percent']} %)").'.'),
            '**Tareas vencidas:** '.self::overdue($data['overdue']),
            '**Ampliaciones pendientes:** '.($data['pending_extensions'] === [] ? 'ninguna.'
                : count($data['pending_extensions']).' ('.self::list($data['pending_extensions'], fn (array $row) => self::clean($row['project']).', pedida por '.self::clean($row['requested_by'])).').'),
            self::hours($data),
        ];
        if ($data['idle_bags'] !== []) {
            $sections[] = '**Bolsas sin horas en la semana:** '.self::list($data['idle_bags'], fn (array $row) => self::project($row)).'.';
        }

        return implode("\n\n", $sections);
    }

    /**
     * The "name + figure" pieces an AI wording must keep verbatim, so it can't move hours from one project or
     * person to another (ComposeWeeklySummary).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function boundFigures(array $data): array
    {
        return [
            ...array_map(fn (array $row) => self::project($row).' '.Duration::humanize($row['seconds']), array_slice($data['by_project'], 0, self::TOP)),
            ...array_map(fn (array $row) => self::clean($row['name']).' '.Duration::humanize($row['seconds']), array_slice($data['by_person'], 0, self::TOP)),
            ...array_map(fn (array $row) => self::project($row)." ({$row['percent']} %)", array_slice($data['bags_running_out'], 0, self::TOP)),
        ];
    }

    /** One plain line: no breaks, no markdown, links, mentions or HTML; capped. */
    public static function clean(string $value): string
    {
        $flat = preg_replace('/[\r\n\t]+/u', ' ', $value) ?? '';
        $flat = preg_replace('#https?://|www\.#iu', '', $flat) ?? ''; // Discord and mail clients link bare URLs
        $flat = preg_replace('/[\[\]()<>@*_~`|#\\\\]/u', '', $flat) ?? '';

        return Str::limit(trim(preg_replace('/\s{2,}/u', ' ', $flat) ?? ''), self::NAME_MAX);
    }

    /** @param  array<string, mixed>  $data */
    private static function hours(array $data): string
    {
        $lines = ['**Horas:** '.Duration::humanize($data['total_seconds']).' (la semana anterior: '.Duration::humanize($data['previous_total_seconds']).').'];
        if ($data['by_project'] !== []) {
            $lines[] = '- Por proyecto: '.self::list($data['by_project'], fn (array $row) => self::project($row).' '.Duration::humanize($row['seconds'])).'.';
            $lines[] = '- Por persona: '.self::list($data['by_person'], fn (array $row) => self::clean($row['name']).' '.Duration::humanize($row['seconds'])).'.';
        }

        return implode("\n", $lines);
    }

    /** @param  array{prefix: string, name: string}  $row */
    private static function project(array $row): string
    {
        return self::clean($row['prefix']).' '.self::clean($row['name']);
    }

    /** @param  array{count: int, oldest: list<array<string, mixed>>}  $overdue */
    private static function overdue(array $overdue): string
    {
        if ($overdue['count'] === 0) {
            return 'ninguna.';
        }
        $oldest = $overdue['oldest'][0];

        return "{$overdue['count']} (la más vieja: ".self::clean($oldest['code']).' «'.self::clean($oldest['title']).'», venció el '.Carbon::parse($oldest['due_on'])->format('d/m').').';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): string  $format
     */
    private static function list(array $rows, callable $format): string
    {
        $shown = array_map($format, array_slice($rows, 0, self::TOP));
        $rest = count($rows) - count($shown);

        return implode('; ', $shown).($rest > 0 ? " y {$rest} más" : '');
    }
}
