<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryRowResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Task "Horas" tab: every entry of the task, by anyone, newest first. Whoever sees the task sees them.
 */
class TaskTimeEntryController extends Controller
{
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        $entries = TimeEntry::query()
            ->where('task_id', $task->id)
            ->with('user:id,first_name,last_name,avatar_path')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(50);

        return TimeEntryRowResource::collection($entries)->additional([
            'meta' => ['total_seconds' => $task->loggedSeconds()],
            'message' => null,
            'errors' => null,
        ]);
    }
}
