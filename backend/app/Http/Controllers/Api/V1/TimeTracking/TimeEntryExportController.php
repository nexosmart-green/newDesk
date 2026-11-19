<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\TimeTracking\Queries\TimeEntryHistory;
use App\Domain\TimeTracking\Support\TimeEntryCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeTracking\TimeEntryHistoryRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Mis horas" → Exportar CSV: the same filter as the list, every page, for Excel.
 */
class TimeEntryExportController extends Controller
{
    public function __invoke(TimeEntryHistoryRequest $request, TimeEntryHistory $history, TimeEntryCsv $csv): StreamedResponse
    {
        $filters = $request->filters();
        $entries = $history->newestFirst($history->query($filters))->lazy(500);

        return response()->streamDownload(
            function () use ($csv, $entries, $filters) {
                $handle = fopen('php://output', 'wb');
                $csv->write($handle, $entries, $filters['timezone']);
                fclose($handle);
            },
            "horas-{$filters['from']}-a-{$filters['to']}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store'],
        );
    }
}
