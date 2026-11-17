<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Queries\HoursReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\HoursReportRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * RF-10.01 (R1): hours per project and period, sold / consumed / % / per person. `projects.manage`.
 * Same query as the MCP tool `project_hours_report`.
 */
class HoursReportController extends Controller
{
    public function __construct(private readonly HoursReport $report) {}

    public function overview(HoursReportRequest $request): JsonResponse
    {
        $period = $request->period();

        return ApiResponse::ok([
            'period' => ['from' => $period->from->toDateString(), 'to' => $period->to->toDateString()],
            'projects' => $this->report->overview($request->user()->company_id, $period->from, $period->to),
        ]);
    }

    /** RF-14.07: the period's hours per client (same query as the MCP tool `hours_report` with group_by=client). */
    public function clients(HoursReportRequest $request): JsonResponse
    {
        $period = $request->period();

        return ApiResponse::ok([
            'period' => ['from' => $period->from->toDateString(), 'to' => $period->to->toDateString()],
            'clients' => $this->report->byClient($request->user()->company_id, $period->from, $period->to),
        ]);
    }

    /** RF-14.07: the period's hours per person across projects (MCP `hours_report` with group_by=person). */
    public function people(HoursReportRequest $request): JsonResponse
    {
        $period = $request->period();

        return ApiResponse::ok([
            'period' => ['from' => $period->from->toDateString(), 'to' => $period->to->toDateString()],
            'people' => $this->report->byPerson($request->user()->company_id, $period->from, $period->to),
        ]);
    }

    public function project(HoursReportRequest $request, Project $project): JsonResponse
    {
        $period = $request->period();

        return ApiResponse::ok($this->report->project($project, $period->from, $period->to));
    }
}
