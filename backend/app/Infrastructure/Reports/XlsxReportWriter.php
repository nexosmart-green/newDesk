<?php

namespace App\Infrastructure\Reports;

use App\Domain\Assistant\Contracts\ReportWriter;
use App\Domain\Assistant\Support\ReportTable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** RF-13.05 Excel: hours as decimal numbers (so they can be summed), a bold header and a title row. */
final class XlsxReportWriter implements ReportWriter
{
    public function format(): string
    {
        return 'xlsx';
    }

    public function write(ReportTable $table): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($table->title, 0, 31));
        $sheet->setCellValue('A1', $table->title);
        $sheet->setCellValue('A2', $table->subtitle);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $types = array_values($table->columns);
        $sheet->fromArray([array_keys($table->columns)], null, 'A4');
        $lastColumn = Coordinate::stringFromColumnIndex(count($types));
        $sheet->getStyle("A4:{$lastColumn}4")->getFont()->setBold(true);

        foreach ($table->rows as $index => $row) {
            $line = 5 + $index;
            foreach ($row as $position => $value) {
                $cell = Coordinate::stringFromColumnIndex($position + 1).$line;
                if ($types[$position] === ReportTable::HOURS && $value !== null) {
                    $sheet->setCellValue($cell, round(((int) $value) / 3600, 2));
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('0.00');
                } else {
                    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                }
            }
        }
        foreach (range(1, count($types)) as $column) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');

        return (string) ob_get_clean();
    }
}
