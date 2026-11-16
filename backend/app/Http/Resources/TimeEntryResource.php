<?php

namespace App\Http\Resources;

use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Http\Request;

/**
 * A worked stretch or the running timer. Times are UTC ISO-8601; `elapsed_seconds` is computed by the
 * server (RF-05.03) together with `server_time` so clients never rely on their own clock.
 *
 * @mixin TimeEntry
 */
class TimeEntryResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['task:id,code,title,project_id', 'project:id,name,code_prefix', 'user:id,first_name,last_name']);

        return [
            'id' => $this->id,
            'is_running' => $this->isRunning(),
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'elapsed_seconds' => $this->elapsedSeconds(),
            'server_time' => now()->toIso8601String(),
            'source' => $this->source->value,
            'channel' => $this->channel->value,
            'comment' => $this->comment,
            'comment_pending' => $this->comment_pending,
            'stop_reason' => $this->stop_reason?->value,
            'stop_reason_label' => $this->stop_reason?->label(),
            'task' => ['id' => $this->task->id, 'code' => $this->task->code, 'title' => $this->task->title],
            'project' => ['id' => $this->project->id, 'name' => $this->project->name],
            'user' => ['id' => $this->user->id, 'full_name' => $this->user->fullName()],
        ];
    }
}
