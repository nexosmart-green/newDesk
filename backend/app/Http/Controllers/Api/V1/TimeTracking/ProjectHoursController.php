<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Actions\AllocateHours;
use App\Domain\TimeTracking\Models\HourAllocation;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;
use App\Domain\TimeTracking\Support\ManualEntryWindow;
use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Project "Horas" tab (RF-05.11): bag summary, allocation history and entries by person/task/date.
 */
class ProjectHoursController extends Controller
{
    public function summary(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $allocations = HourAllocation::query()->where('project_id', $project->id)->with('creator')->latest('id')->get()
            ->map(fn (HourAllocation $allocation) => [
                'id' => $allocation->id,
                'seconds' => $allocation->seconds,
                'reason' => $allocation->reason,
                'reference' => $allocation->reference,
                'created_by' => $allocation->creator->fullName(),
                'created_at' => $allocation->created_at->toIso8601String(),
            ]);

        return ApiResponse::ok([...HourBag::of($project)->toArray(), 'allocations' => $allocations]);
    }

    public function entries(Request $request, Project $project, ManualEntryWindow $window): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);
        $request->validate([
            'user_id' => ['nullable', 'integer'],
            'task_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $entries = TimeEntry::query()->where('project_id', $project->id)
            ->when($request->integer('user_id'), fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($request->integer('task_id'), fn (Builder $q, int $id) => $q->where('task_id', $id))
            ->startedOnLocalDays($request->query('from'), $request->query('to'), $request->user()->effectiveTimezone())
            ->with(['task', 'project', 'user'])
            ->latest('started_at')
            ->paginate(50);

        return TimeEntryResource::collection($entries)->additional([
            'meta' => ['editable_since' => $window->editableSince($request->user())?->toIso8601String()],
            'message' => null,
            'errors' => null,
        ]);
    }

    public function allocate(Request $request, Project $project, AllocateHours $allocateHours): JsonResponse
    {
        Gate::authorize('view', $project);
        $request->validate([
            'seconds' => ['required', 'integer', 'not_in:0', 'between:-36000000,36000000'],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $allocateHours($request->user(), $project, $request->integer('seconds'), $request->string('reason')->value(), $request->input('reference'));

        return ApiResponse::ok(HourBag::of($project->fresh())->toArray(), 'Horas cargadas en la bolsa.', 201);
    }
}
