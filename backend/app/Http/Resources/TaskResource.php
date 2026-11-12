<?php

namespace App\Http\Resources;

use App\Domain\People\Actions\ReplaceAvatar;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskLabel;
use App\Domain\TimeTracking\Support\HourBagRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Task row (list/board). Detail (`tasks.show`) adds description (markdown), checklist and who/when created it.
 * Eager load Task::RESOURCE_RELATIONS (and `withLoggedSeconds()->withBadgeCounts()` in lists) before using it;
 * the detail also loads `creator` and `checklistItems.doneBy`.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isDetail = $request->routeIs('tasks.show');

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
            'is_overdue' => $this->isOverdue($request->user()?->localDate()),
            'estimate_seconds' => $this->estimate_seconds,
            'logged_seconds' => $this->resource->loggedSeconds(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null,
            'labels' => $this->labels->map(fn (TaskLabel $label) => $label->only('id', 'name', 'color'))->values(),
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map(fn ($user) => [
                'id' => $user->id,
                'full_name' => $user->fullName(),
                'avatar' => ReplaceAvatar::urls($user->avatar_path),
            ])->values()),
            'checklist_progress' => $this->resource->checklistProgress(),
            'attachments_count' => $this->resource->attachmentsCount(),
            'description' => $this->when($isDetail, $this->description),
            'checklist' => $this->when($isDetail, fn () => TaskChecklistItemResource::collection($this->checklistItems)),
            'created_by' => $this->when($isDetail, fn () => $this->creator
                ? ['id' => $this->creator->id, 'full_name' => $this->creator->fullName()]
                : null),
            'created_at' => $this->when($isDetail, fn () => $this->created_at?->toIso8601String()),
        ];
    }
}
