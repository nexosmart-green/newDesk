<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Queries\HoursReport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * RF-10.01 (R1): hours per project and period, sold / consumed / % / per person. `projects.manage`.
 * Same query as the MCP tool `project_hours_report`.
 */
class HoursReportController extends Controller
{
    public function __construct(private readonly HoursReport $report) {}

    public function overview(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);
        [$from, $to] = $this->period($request);

        return ApiResponse::ok([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'projects' => $this->report->overview($request->user()->company_id, $from, $to),
        ]);
    }

    public function project(Request $request, Project $project): JsonResponse
    {
        Gate::authorize(Permission::ProjectsManage->value);
        Gate::authorize('view', $project);
        [$from, $to] = $this->period($request);

        return ApiResponse::ok($this->report->project($project, $from, $to));
    }

    /** @return array{0: Carbon, 1: Carbon} default: current month, in the viewer's timezone */
    private function period(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $timezone = $request->user()->effectiveTimezone();

        return [
            $request->filled('from') ? Carbon::parse($request->string('from')->value(), $timezone) : now($timezone)->startOfMonth(),
            $request->filled('to') ? Carbon::parse($request->string('to')->value(), $timezone) : now($timezone)->startOfDay(),
        ];
    }
}
