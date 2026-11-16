<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\CompletePendingComment;
use App\Domain\TimeTracking\Actions\CorrectTimeEntry;
use App\Domain\TimeTracking\Actions\DeleteTimeEntry;
use App\Domain\TimeTracking\Actions\LogManualTime;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Queries\Timesheet;
use App\Domain\TimeTracking\Support\LocalDateRange;
use App\Domain\TimeTracking\Support\ManualEntryWindow;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeTracking\CompletePendingCommentRequest;
use App\Http\Requests\TimeTracking\CorrectTimeEntryRequest;
use App\Http\Requests\TimeTracking\LogManualTimeRequest;
use App\Http\Requests\TimeTracking\TimesheetRequest;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
        $person = $this->personAsked($request);

        $query = TimeEntry::query()->where('user_id', $person->id)
            ->when($request->integer('project_id'), fn (Builder $q, int $id) => $q->where('project_id', $id));
        $entries = LocalDateRange::of($request->query('from'), $request->query('to'), $person->effectiveTimezone())->apply($query)
            ->with(['task', 'project', 'user'])
            ->latest('started_at')
            ->paginate(50);

        return TimeEntryResource::collection($entries)
            ->additional(['meta' => ['editable_since' => $window->editableSince($request->user())?->toIso8601String()]]);
    }

    /** RF-05.09 */
    public function store(LogManualTimeRequest $request, Task $task, LogManualTime $logManualTime): JsonResponse
    {
        [$startedAt, $endedAt] = $request->utcRange();
        $entry = $logManualTime($request->user(), $task, $startedAt, $endedAt, $request->validated('comment'));

        return ApiResponse::ok(TimeEntryResource::make($entry), 'Horas cargadas.', 201);
    }

    /** RF-05.15; RF-05.20 may also move it to another task. */
    public function update(CorrectTimeEntryRequest $request, TimeEntry $timeEntry, CorrectTimeEntry $correct): JsonResponse
    {
        [$startedAt, $endedAt] = $request->utcRange();
        $task = $request->filled('task_id') ? Task::query()->findOrFail($request->integer('task_id')) : null;
        $entry = $correct($request->user(), $timeEntry, $startedAt, $endedAt, $request->string('comment')->value(), $task);

        return ApiResponse::ok(TimeEntryResource::make($entry), 'Registro corregido.');
    }

    public function destroy(Request $request, TimeEntry $timeEntry, DeleteTimeEntry $delete): JsonResponse
    {
        Gate::authorize('delete', $timeEntry);

        $delete($request->user(), $timeEntry);

        return ApiResponse::ok(message: "Registro de {$timeEntry->task->code} borrado: sus horas vuelven a la bolsa.");
    }

    /** RF-05.17 */
    public function completeComment(CompletePendingCommentRequest $request, TimeEntry $timeEntry, CompletePendingComment $complete): JsonResponse
    {
        $entry = $complete($request->user(), $timeEntry, $request->validated('comment'));

        return ApiResponse::ok(TimeEntryResource::make($entry), 'Comentario guardado.');
    }

    /** RF-05.20 "Mis horas" calendar: what the person worked on each of their local days. */
    public function days(Request $request, Timesheet $timesheet): JsonResponse
    {
        $request->validate([
            'user_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        if ($request->date('from')->diffInDays($request->date('to')) > 62) {
            throw ValidationException::withMessages(['to' => 'Elegí un rango de hasta 62 días.']);
        }

        $sheet = $timesheet($this->personAsked($request), $request->string('from')->value(), $request->string('to')->value(), $request->integer('project_id') ?: null);

        return ApiResponse::ok([
            ...$sheet,
            'days' => array_map(fn (array $day) => ['date' => $day['date'], 'total_seconds' => $day['total_seconds']], $sheet['days']),
        ]);
    }

    /** RF-05.12 "Mi semana". */
    public function timesheet(TimesheetRequest $request, Timesheet $timesheet): JsonResponse
    {
        $range = $request->range();

        return ApiResponse::ok($timesheet($request->user(), $range->from, $range->to));
    }

    /** RF-05.20: me by default; someone else's hours only for `time.manage`. */
    private function personAsked(Request $request): User
    {
        $viewer = $request->user();
        $userId = $request->integer('user_id') ?: $viewer->id;
        if ($userId === $viewer->id) {
            return $viewer;
        }
        if (! $viewer->hasPermission(Permission::TimeManage)) {
            throw new AuthorizationException('Solo un administrador ve las horas de otras personas.');
        }

        return User::query()->where('company_id', $viewer->company_id)->findOrFail($userId);
    }
}
