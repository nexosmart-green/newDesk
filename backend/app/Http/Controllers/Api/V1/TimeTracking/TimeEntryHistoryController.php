<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\TimeTracking\Queries\TimeEntryHistory;
use App\Domain\TimeTracking\Support\TimeEntryCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeTracking\TimeEntryHistoryRequest;
use App\Http\Resources\TimeEntryRowResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Mi semana" → "Historial": own entries over a free range; `time.manage` may pick another person.
 */
class TimeEntryHistoryController extends Controller
{
    public function __construct(private readonly TimeEntryHistory $history) {}

    public function index(TimeEntryHistoryRequest $request): AnonymousResourceCollection
    {
        $filters = $request->filters();

        return TimeEntryRowResource::collection($this->history->paginate($filters))->additional([
            'meta' => ['total_seconds' => $this->history->totalSeconds($filters)],
            'message' => null,
            'errors' => null,
        ]);
    }

    public function export(TimeEntryHistoryRequest $request, TimeEntryCsv $csv): StreamedResponse
    {
        $filters = $request->filters();
        $entries = $this->history->newestFirst($this->history->query($filters))->lazy(500);

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
