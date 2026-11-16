<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\StartTimer;
use App\Domain\TimeTracking\Actions\StopTimer;
use App\Domain\TimeTracking\Models\DayClock;
use App\Domain\TimeTracking\Queries\TimerState;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeTracking\StartTimerRequest;
use App\Http\Requests\TimeTracking\StopTimerRequest;
use App\Http\Resources\DayClockResource;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The header timer (RF-05.01). Same state from every tab and device (CA-05.03).
 */
class TimerController extends Controller
{
    public function show(Request $request, TimerState $timerState): JsonResponse
    {
        $user = $request->user();
        ['active' => $active, 'pending' => $pending] = $timerState($user);
        // RF-05.19: running or waiting to be split (invariant 7: at most one).
        $day = DayClock::query()->where('user_id', $user->id)->unsplit()->with('user.company')->first();

        return ApiResponse::ok([
            'active' => $active ? TimeEntryResource::make($active) : null,
            'pending_comments' => TimeEntryResource::collection($pending),
            'day_clock' => $day ? DayClockResource::make($day) : null,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function start(StartTimerRequest $request, Task $task, StartTimer $startTimer): JsonResponse
    {
        $entry = $startTimer($request->user(), $task, $request->validated('previous_comment'));

        return ApiResponse::ok(TimeEntryResource::make($entry), "Cronómetro iniciado en {$task->code}.");
    }

    public function stop(StopTimerRequest $request, StopTimer $stopTimer): JsonResponse
    {
        $entry = $stopTimer($request->user(), $request->validated('comment'));

        return ApiResponse::ok(TimeEntryResource::make($entry), 'Cronómetro detenido.');
    }
}
