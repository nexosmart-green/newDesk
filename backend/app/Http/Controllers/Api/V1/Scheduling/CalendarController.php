<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Scheduling\Actions\IssueCalendarFeed;
use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Scheduling\Queries\Calendar;
use App\Domain\Scheduling\Support\ICalendar;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * RF-06.04 "Mi calendario", RF-06.06 holidays, RF-06.07 iCal feed.
 */
class CalendarController extends Controller
{
    public function mine(Request $request, Calendar $calendar): JsonResponse
    {
        $range = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);

        return ApiResponse::ok($calendar->forPerson($request->user(), $range['from'], $range['to']));
    }

    public function holidays(Request $request): JsonResponse
    {
        $request->validate(['year' => ['required', 'integer', 'between:2000,2100']]);
        $year = $request->integer('year');

        return ApiResponse::ok(Holiday::query()->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])->orderBy('date')->get()
            ->map(fn (Holiday $holiday) => $holiday->only('id', 'name', 'scope') + ['date' => $holiday->date->toDateString()]));
    }

    public function feedStatus(Request $request, IssueCalendarFeed $feed): JsonResponse
    {
        return ApiResponse::ok(['active' => $feed->isActive($request->user())]);
    }

    public function storeHoliday(Request $request): JsonResponse
    {
        Gate::authorize(Permission::CompanyManage->value);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::in(['national', 'company'])],
        ]);

        $holiday = Holiday::query()->firstOrCreate(['company_id' => $request->user()->company_id, 'date' => $data['date'], 'name' => $data['name']], $data);

        return ApiResponse::ok($holiday->only('id', 'name', 'scope') + ['date' => $holiday->date->toDateString()], 'Feriado guardado.', 201);
    }

    public function destroyHoliday(Holiday $holiday): JsonResponse
    {
        Gate::authorize(Permission::CompanyManage->value);
        $holiday->delete();

        return ApiResponse::ok(message: 'Feriado borrado.');
    }

    /** Issues (or rotates) the personal feed URL. Shown once. */
    public function issueFeed(Request $request, IssueCalendarFeed $feed): JsonResponse
    {
        $token = $feed->issue($request->user());

        return ApiResponse::ok(['url' => rtrim((string) config('app.url'), '/')."/calendar/{$token}.ics"], 'Suscribite a esta URL desde Google Calendar o el celular. Generar otra invalida la anterior.', 201);
    }

    public function revokeFeed(Request $request, IssueCalendarFeed $feed): JsonResponse
    {
        $feed->revoke($request->user());

        return ApiResponse::ok(message: 'Feed revocado.');
    }

    /** Public, token-authenticated .ics: only the token owner's own data (CA-06.07). */
    public function feed(string $token, IssueCalendarFeed $feed, Calendar $calendar): Response
    {
        $user = $feed->userFor($token) ?? abort(404);
        $timezone = $user->company->timezone;
        $data = $calendar->forPerson($user, now($timezone)->subMonths(2)->toDateString(), now($timezone)->addMonths(6)->toDateString());

        $ical = new ICalendar("newDesk — {$user->fullName()}");
        foreach ($data['shifts'] as $shift) {
            $ical->timed("shift-{$shift['id']}@newdesk", 'Turno '.($shift['name'] ?? '')." ({$shift['mode_label']})", Carbon::parse($shift['start_utc']), Carbon::parse($shift['end_utc']));
        }
        foreach ($data['tasks_due'] as $task) {
            $ical->allDay("due-{$task['code']}@newdesk", "Vence {$task['code']}: {$task['title']}", $task['due_on']);
        }
        foreach ($data['holidays'] as $holiday) {
            $ical->allDay("holiday-{$holiday['id']}@newdesk", "Feriado: {$holiday['name']}", $holiday['date']);
        }

        return response($ical->render(), 200, ['Content-Type' => 'text/calendar; charset=utf-8']);
    }
}
