<?php

namespace App\Infrastructure\Reports;

use App\Domain\Assistant\Contracts\ReportWriter;
use App\Domain\Assistant\Support\ReportTable;
use App\Domain\Shared\Support\Duration;
use Dompdf\Dompdf;
use Dompdf\Options;

/** RF-13.05 PDF: a plain, printable table; hours as "12h 30m". Remote resources are off (no fetches). */
final class PdfReportWriter implements ReportWriter
{
    public function format(): string
    {
        return 'pdf';
    }

    public function write(ReportTable $table): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($table), 'UTF-8');
        $dompdf->setPaper('A4', count($table->columns) > 4 ? 'landscape' : 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function html(ReportTable $table): string
    {
        $types = array_values($table->columns);
        $headers = implode('', array_map(fn (string $header, string $type) => '<th class="'.$type.'">'.e($header).'</th>', array_keys($table->columns), $types));
        $rows = implode('', array_map(fn (array $row) => '<tr>'.implode('', array_map(
            fn ($value, int $position) => $types[$position] === ReportTable::HOURS
                ? '<td class="hours">'.($value === null ? '' : Duration::humanize((int) $value)).'</td>'
                : '<td>'.e((string) $value).'</td>',
            $row, array_keys($row),
        )).'</tr>', $table->rows));
        $empty = $table->rows === [] ? '<p class="empty">Sin datos para este período.</p>' : '';

        return <<<HTML
            <html><head><meta charset="utf-8"><style>
                body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #171f29; }
                h1 { font-size: 16px; margin: 0 0 2px; } p.sub { color: #5b6573; margin: 0 0 12px; }
                table { width: 100%; border-collapse: collapse; }
                th { text-align: left; border-bottom: 1.5px solid #309798; padding: 5px 6px; }
                td { border-bottom: 0.5px solid #dde2e8; padding: 4px 6px; }
                .hours { text-align: right; white-space: nowrap; } .empty { color: #5b6573; }
            </style></head><body>
                <h1>{$this->escape($table->title)}</h1><p class="sub">{$this->escape($table->subtitle)}</p>
                <table><thead><tr>{$headers}</tr></thead><tbody>{$rows}</tbody></table>{$empty}
            </body></html>
            HTML;
    }

    private function escape(string $value): string
    {
        return e($value);
    }
}
