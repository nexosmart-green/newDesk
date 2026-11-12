<?php

namespace App\Http\Controllers\Api\V1\TimeTracking;

use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Queries\TaskTimeEntries;
use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryRowResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Task "Horas" tab: every entry of the task, by anyone, newest first. Whoever sees the task sees them.
 */
class TaskTimeEntryController extends Controller
{
    public function index(Task $task, TaskTimeEntries $entries): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return TimeEntryRowResource::collection($entries->paginate($task))
            ->additional(['meta' => ['total_seconds' => $task->loggedSeconds()]]);
    }
}
