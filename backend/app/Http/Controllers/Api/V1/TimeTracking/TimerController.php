<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Actions\StartTimer;
use App\Domain\TimeTracking\Actions\StopTimer;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The header timer (RF-05.01). Same state from every tab and device (CA-05.03).
 */
class TimerController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $active = TimeEntry::query()->where('user_id', $user->id)->running()->first();
        $pending = TimeEntry::query()->where('user_id', $user->id)->where('comment_pending', true)->orderBy('started_at')->get();

        return ApiResponse::ok([
            'active' => $active ? TimeEntryResource::make($active) : null,
            'pending_comments' => TimeEntryResource::collection($pending),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function start(Request $request, Task $task, StartTimer $startTimer): JsonResponse
    {
        Gate::authorize('view', $task);
        $request->validate(['previous_comment' => ['nullable', 'string', 'max:5000']]);

        $entry = $startTimer($request->user(), $task, $request->input('previous_comment'));

        return ApiResponse::ok(TimeEntryResource::make($entry), "Cronómetro iniciado en {$task->code}.");
    }

    public function stop(Request $request, StopTimer $stopTimer): JsonResponse
    {
        $request->validate(['comment' => ['nullable', 'string', 'max:5000']]);

        $entry = $stopTimer($request->user(), $request->input('comment'));

        return ApiResponse::ok(TimeEntryResource::make($entry), 'Cronómetro detenido.');
    }
}
