<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\SplitDayClock;
use App\Domain\TimeTracking\Actions\StartDayClock;
use App\Domain\TimeTracking\Actions\StopDayClock;
use App\Domain\TimeTracking\Models\DayClock;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeTracking\SplitDayRequest;
use App\Http\Resources\DayClockResource;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** RF-05.19 "Reloj del día": web only (MCP shows it in whoami / get_active_timer). */
class DayClockController extends Controller
{
    public function start(Request $request, StartDayClock $startDay): JsonResponse
    {
        return ApiResponse::ok(DayClockResource::make($startDay($request->user())), 'Día iniciado.', 201);
    }

    public function stop(Request $request, StopDayClock $stopDay): JsonResponse
    {
        return ApiResponse::ok(DayClockResource::make($stopDay($request->user())), 'Día frenado: repartilo entre tus proyectos.');
    }

    public function split(SplitDayRequest $request, DayClock $dayClock, SplitDayClock $split): JsonResponse
    {
        Gate::authorize('split', $dayClock);
        $parts = $request->parts();
        // A new task needs the same right as creating it in the project (member or projects.manage).
        foreach ($parts as $part) {
            if ($part['task_id'] === null && ($project = Project::query()->find($part['project_id'])) !== null) {
                Gate::authorize('create', [Task::class, $project]);
            }
        }

        $result = $split($request->user(), $dayClock, $parts);

        return ApiResponse::ok([
            'entries' => TimeEntryResource::collection($result['entries']),
            'overdrawn' => $result['overdrawn'],
        ], $parts === [] ? 'Día cerrado sin horas.' : 'Día repartido.');
    }
}
