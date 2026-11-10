<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\CompletePendingComment;
use App\Domain\TimeTracking\Actions\CorrectTimeEntry;
use App\Domain\TimeTracking\Actions\DeleteTimeEntry;
use App\Domain\TimeTracking\Actions\LogManualTime;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Queries\Timesheet;
use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class TimeEntryController extends Controller
{
    /** RF-05.09: date + from/to are in the person's timezone; stored in UTC. */
    public function store(Request $request, Task $task, LogManualTime $logManualTime): JsonResponse
    {
        Gate::authorize('view', $task);
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['required', 'date_format:H:i'],
            'to' => ['required', 'date_format:H:i'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        [$startedAt, $endedAt] = $this->toUtcRange($request);
        $entry = $logManualTime($request->user(), $task, $startedAt, $endedAt, $request->input('comment'));

        return ApiResponse::ok(TimeEntryResource::make($entry), 'Horas cargadas.', 201);
    }

    public function update(Request $request, TimeEntry $timeEntry, CorrectTimeEntry $correct): JsonResponse
    {
        Gate::authorize('update', $timeEntry);
        $request->validate([
            'date' => ['required_with:from,to', 'date_format:Y-m-d'],
            'from' => ['required_with:date', 'date_format:H:i'],
            'to' => ['required_with:date', 'date_format:H:i'],
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        [$startedAt, $endedAt] = $request->filled('date') ? $this->toUtcRange($request) : [null, null];
        $entry = $correct($request->user(), $timeEntry, $startedAt, $endedAt, $request->string('comment')->value());

        return ApiResponse::ok(TimeEntryResource::make($entry), 'Registro corregido.');
    }

    public function destroy(Request $request, TimeEntry $timeEntry, DeleteTimeEntry $delete): JsonResponse
    {
        Gate::authorize('delete', $timeEntry);

        $delete($request->user(), $timeEntry);

        return ApiResponse::ok(message: 'Registro borrado.');
    }

    /** RF-05.17 */
    public function completeComment(Request $request, TimeEntry $timeEntry, CompletePendingComment $complete): JsonResponse
    {
        $request->validate(['comment' => ['nullable', 'string', 'max:5000']]);

        $entry = $complete($request->user(), $timeEntry, $request->input('comment'));

        return ApiResponse::ok(TimeEntryResource::make($entry), 'Comentario guardado.');
    }

    /** RF-05.12 "Mi semana". */
    public function timesheet(Request $request, Timesheet $timesheet): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return ApiResponse::ok($timesheet($request->user(), $request->string('from')->value(), $request->string('to')->value()));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function toUtcRange(Request $request): array
    {
        $timezone = $request->user()->effectiveTimezone();
        $date = $request->string('date')->value();

        return [
            Carbon::createFromFormat('Y-m-d H:i', "{$date} {$request->string('from')}", $timezone)->utc(),
            Carbon::createFromFormat('Y-m-d H:i', "{$date} {$request->string('to')}", $timezone)->utc(),
        ];
    }
}
