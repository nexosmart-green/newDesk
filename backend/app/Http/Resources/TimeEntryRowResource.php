<?php

namespace App\Http\Resources;

use App\Domain\People\Actions\ReplaceAvatar;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Http\Request;

/**
 * Read-only row of the task "Horas" tab and of "Historial de horas". Never includes rates.
 * `task` and `project` appear only when loaded (the task tab already knows them).
 *
 * @mixin TimeEntry
 */
class TimeEntryRowResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'full_name' => $this->user->fullName(),
                'avatar' => ReplaceAvatar::urls($this->user->avatar_path),
            ],
            'task' => $this->whenLoaded('task', fn () => ['code' => $this->task->code, 'title' => $this->task->title]),
            'project' => $this->whenLoaded('project', fn () => ['id' => $this->project->id, 'name' => $this->project->name]),
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'seconds' => $this->elapsedSeconds(),
            'is_running' => $this->isRunning(),
            'comment' => $this->comment,
            'comment_pending' => $this->comment_pending,
            'source' => $this->source->value,
        ];
    }
}
