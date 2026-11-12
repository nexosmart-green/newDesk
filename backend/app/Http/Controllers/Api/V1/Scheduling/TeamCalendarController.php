<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Domain\Scheduling\Queries\TeamCalendar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\TeamCalendarRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * RF-06.05 "Calendario de equipo": who works each day. Managers see everyone; leaders, their reports.
 */
class TeamCalendarController extends Controller
{
    public function __invoke(TeamCalendarRequest $request, TeamCalendar $calendar): JsonResponse
    {
        $viewer = $request->user();
        $people = $calendar->audienceOf($viewer);
        Gate::allowIf($people->isNotEmpty(), 'El calendario de equipo es para quien gestiona turnos o tiene gente a cargo.');

        $company = $viewer->company;

        return ApiResponse::ok($calendar($people, $company->id, $company->timezone, $request->string('from')->value(), $request->string('to')->value()));
    }
}
