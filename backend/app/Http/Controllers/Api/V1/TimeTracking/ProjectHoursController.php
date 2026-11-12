<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Actions\AllocateHours;
use App\Domain\TimeTracking\Models\HourAllocation;
use App\Domain\TimeTracking\Queries\ProjectTimeEntries;
use App\Domain\TimeTracking\Support\HourBag;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeTracking\AllocateHoursRequest;
use App\Http\Requests\TimeTracking\ProjectTimeEntriesRequest;
use App\Http\Resources\HourAllocationResource;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
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

        $allocations = HourAllocation::query()->where('project_id', $project->id)->with('creator')->latest('id')->get();

        return ApiResponse::ok([...HourBag::of($project)->toArray(), 'allocations' => HourAllocationResource::collection($allocations)]);
    }

    public function entries(ProjectTimeEntriesRequest $request, Project $project, ProjectTimeEntries $entries): AnonymousResourceCollection
    {
        return TimeEntryResource::collection($entries->paginate($project, $request->filters(), $request->user()->effectiveTimezone()));
    }

    public function allocate(AllocateHoursRequest $request, Project $project, AllocateHours $allocateHours): JsonResponse
    {
        $allocateHours($request->user(), $project, $request->integer('seconds'), $request->string('reason')->value(), $request->validated('reference'));

        return ApiResponse::ok(HourBag::of($project->fresh())->toArray(), 'Horas cargadas en la bolsa.', 201);
    }
}
