<?php

namespace App\Http\Resources;

use App\Domain\People\Actions\ReplaceAvatar;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Support\HourBagRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Task row (list/board). Detail adds description and comments.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'project' => $this->whenLoaded('project', fn () => [
                ...$this->project->only('id', 'name', 'code_prefix'),
                // RF-05.05: lets the ▶ button show "Sin horas disponibles" before the click.
                'hours_exhausted' => app(HourBagRegistry::class)->for($this->project)->isExhausted(),
            ]),
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'name' => $this->status->name,
                'color' => $this->status->color,
                'kind' => $this->status->kind->value,
            ]),
            'priority' => $this->priority->value,
            'starts_on' => $this->starts_on?->toDateString(),
            'due_on' => $this->due_on?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'estimate_seconds' => $this->estimate_seconds,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map(fn ($user) => [
                'id' => $user->id,
                'full_name' => $user->fullName(),
                'avatar' => ReplaceAvatar::urls($user->avatar_path),
            ])->values()),
            'description' => $this->when($request->routeIs('tasks.show'), $this->description),
        ];
    }
}
