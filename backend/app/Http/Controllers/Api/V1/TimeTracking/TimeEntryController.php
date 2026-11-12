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
use App\Http\Requests\TimeTracking\CompletePendingCommentRequest;
use App\Http\Requests\TimeTracking\CorrectTimeEntryRequest;
use App\Http\Requests\TimeTracking\LogManualTimeRequest;
use App\Http\Requests\TimeTracking\TimesheetRequest;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TimeEntryController extends Controller
{
    /** RF-05.09 */
    public function store(LogManualTimeRequest $request, Task $task, LogManualTime $logManualTime): JsonResponse
    {
        [$startedAt, $endedAt] = $request->utcRange();
        $entry = $logManualTime($request->user(), $task, $startedAt, $endedAt, $request->validated('comment'));

        return ApiResponse::ok(TimeEntryResource::make($entry), 'Horas cargadas.', 201);
    }

    /** RF-05.15 */
    public function update(CorrectTimeEntryRequest $request, TimeEntry $timeEntry, CorrectTimeEntry $correct): JsonResponse
    {
        [$startedAt, $endedAt] = $request->utcRange();
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
    public function completeComment(CompletePendingCommentRequest $request, TimeEntry $timeEntry, CompletePendingComment $complete): JsonResponse
    {
        $entry = $complete($request->user(), $timeEntry, $request->validated('comment'));

        return ApiResponse::ok(TimeEntryResource::make($entry), 'Comentario guardado.');
    }

    /** RF-05.12 "Mi semana". */
    public function timesheet(TimesheetRequest $request, Timesheet $timesheet): JsonResponse
    {
        $range = $request->range();

        return ApiResponse::ok($timesheet($request->user(), $range->from, $range->to));
    }
}
