<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Scheduling\Actions\DeleteHoliday;
use App\Domain\Scheduling\Actions\IssueCalendarFeed;
use App\Domain\Scheduling\Actions\RevokeCalendarFeed;
use App\Domain\Scheduling\Actions\SaveHoliday;
use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Scheduling\Queries\Calendar;
use App\Domain\Scheduling\Queries\CalendarFeedLookup;
use App\Domain\Scheduling\Support\PersonalCalendarFeed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\CalendarRangeRequest;
use App\Http\Requests\Scheduling\HolidayListRequest;
use App\Http\Requests\Scheduling\SaveHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * RF-06.04 "Mi calendario", RF-06.06 holidays, RF-06.07 iCal feed.
 */
class CalendarController extends Controller
{
    public function mine(CalendarRangeRequest $request, Calendar $calendar): JsonResponse
    {
        $range = $request->range();

        return ApiResponse::ok($calendar->forPerson($request->user(), $range->from, $range->to));
    }

    public function holidays(HolidayListRequest $request): AnonymousResourceCollection
    {
        $year = $request->integer('year');

        return HolidayResource::collection(Holiday::query()->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])->orderBy('date')->get());
    }

    public function storeHoliday(SaveHolidayRequest $request, SaveHoliday $save): JsonResponse
    {
        return ApiResponse::ok(HolidayResource::make($save($request->user(), $request->validated())), 'Feriado guardado.', 201);
    }

    public function destroyHoliday(Request $request, Holiday $holiday, DeleteHoliday $delete): JsonResponse
    {
        Gate::authorize(Permission::CompanyManage->value);
        $delete($request->user(), $holiday);

        return ApiResponse::ok(message: 'Feriado borrado.');
    }

    public function feedStatus(Request $request, CalendarFeedLookup $feeds): JsonResponse
    {
        return ApiResponse::ok(['active' => $feeds->isActive($request->user())]);
    }

    /** Issues (or rotates) the personal feed URL. Shown once. */
    public function issueFeed(Request $request, IssueCalendarFeed $issue): JsonResponse
    {
        $token = $issue($request->user());

        return ApiResponse::ok(['url' => rtrim((string) config('app.url'), '/')."/calendar/{$token}.ics"], 'Suscribite a esta URL desde Google Calendar o el celular. Generar otra invalida la anterior.', 201);
    }

    public function revokeFeed(Request $request, RevokeCalendarFeed $revoke): JsonResponse
    {
        $revoke($request->user());

        return ApiResponse::ok(message: 'Feed revocado.');
    }

    /** Public, token-authenticated .ics: only the token owner's own data (CA-06.07). */
    public function feed(string $token, CalendarFeedLookup $feeds, PersonalCalendarFeed $calendarFeed): Response
    {
        $user = $feeds->userFor($token) ?? abort(404);

        return response($calendarFeed->render($user), 200, ['Content-Type' => 'text/calendar; charset=utf-8']);
    }
}
