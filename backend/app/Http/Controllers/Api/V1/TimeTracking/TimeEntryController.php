<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\CompletePendingComment;
use App\Domain\TimeTracking\Actions\CorrectTimeEntry;
use App\Domain\TimeTracking\Actions\DeleteTimeEntry;
use App\Domain\TimeTracking\Actions\LogManualTime;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Queries\Timesheet;
use App\Domain\TimeTracking\Support\ManualEntryWindow;
use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class TimeEntryController extends Controller
{
    /** RF-05.20 "Mis horas": my entries from every project; `time.manage` picks anyone's. */
    public function index(Request $request, ManualEntryWindow $window): AnonymousResourceCollection
    {
        $request->validate([
            'user_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $viewer = $request->user();
        $userId = $request->integer('user_id') ?: $viewer->id;
        if ($userId !== $viewer->id && ! $viewer->hasPermission(Permission::TimeManage)) {
            throw new AuthorizationException('Solo un administrador ve las horas de otras personas.');
        }

        $entries = TimeEntry::query()->where('user_id', $userId)
            ->when($request->integer('project_id'), fn (Builder $q, int $id) => $q->where('project_id', $id))
            ->startedOnLocalDays($request->query('from'), $request->query('to'), $viewer->effectiveTimezone())
            ->with(['task', 'project', 'user'])
            ->latest('started_at')
            ->paginate(50);

        return TimeEntryResource::collection($entries)->additional([
            'meta' => ['editable_since' => $window->editableSince($viewer)?->toIso8601String()],
            'message' => null,
            'errors' => null,
        ]);
    }

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
            'task_id' => ['nullable', 'integer'],
        ]);

        [$startedAt, $endedAt] = $request->filled('date') ? $this->toUtcRange($request) : [null, null];
        $task = $request->filled('task_id') ? Task::query()->findOrFail($request->integer('task_id')) : null;
        $entry = $correct($request->user(), $timeEntry, $startedAt, $endedAt, $request->string('comment')->value(), $task);

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
