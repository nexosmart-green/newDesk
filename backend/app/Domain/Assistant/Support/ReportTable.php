<?php

namespace App\Domain\Assistant\Support;

/**
 * A report ready to be written as xlsx or pdf (RF-14.05). Columns are typed so each format shows hours its own
 * way: a number in Excel (to sum), "12h 30m" in the PDF. Hours are always integer seconds here.
 */
final class ReportTable
{
    public const TEXT = 'text';

    public const HOURS = 'hours';

    /**
     * @param  array<string, string>  $columns  header => type (TEXT | HOURS)
     * @param  list<list<string|int|null>>  $rows
     */
    public function __construct(
        public readonly string $title,
        public readonly string $subtitle,
        public readonly array $columns,
        public readonly array $rows,
    ) {}
}
