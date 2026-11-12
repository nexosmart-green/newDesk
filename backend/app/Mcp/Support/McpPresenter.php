<?php

namespace App\Mcp\Support;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;

/**
 * Compact, model-friendly shapes. Never includes rates or other people's personal data (RF-12 Seguridad).
 */
final class McpPresenter
{
    /** @return array<string, mixed> */
    public static function task(Task $task): array
    {
        $task->loadMissing(['status', 'assignees', 'project', 'category', 'labels']);
        $logged = $task->loggedSeconds();

        return [
            'code' => $task->code,
            'title' => $task->title,
            'project' => $task->project->code_prefix,
            'status' => $task->status->name,
            'priority' => $task->priority->value,
            'due_on' => $task->due_on?->toDateString(),
            'overdue' => $task->isOverdue(auth()->user()?->localDate()),
            'assignees' => $task->assignees->map(fn ($user) => $user->fullName())->values()->all(),
            'logged' => self::hours($logged),
            'logged_seconds' => $logged,
            'category' => $task->category?->name,
            'labels' => $task->labels->pluck('name')->all(),
        ];
    }

    /**
     * RF-04.08: numbered as the UI shows it, so the model can refer to "el ítem 3".
     *
     * @return list<array{n: int, text: string, done: bool}>
     */
    public static function checklist(Task $task): array
    {
        return $task->checklistItems()->get(['id', 'text', 'done_at'])->values()
            ->map(fn ($item, int $index) => ['n' => $index + 1, 'text' => $item->text, 'done' => $item->isDone()])->all();
    }

    /** @return array<string, mixed> */
    public static function project(Project $project): array
    {
        $bag = HourBag::of($project);

        return [
            'prefix' => $project->code_prefix,
            'name' => $project->name,
            'status' => $project->status->label(),
            'type' => $project->type->value,
            'hours' => [
                'allocated' => $bag->hasCap() ? self::hours($bag->allocated()) : 'sin tope (interno)',
                'consumed' => self::hours($bag->consumed()),
                'available' => $bag->hasCap() ? self::hours((int) $bag->available()) : 'sin tope',
                'percent_consumed' => $bag->percentConsumed(),
                'blocked' => $bag->isExhausted(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function entry(TimeEntry $entry): array
    {
        $entry->loadMissing('task');

        return [
            'id' => $entry->id,
            'task' => $entry->task->code,
            'running' => $entry->isRunning(),
            'started_at' => $entry->started_at->toIso8601String(),
            'elapsed' => self::hours($entry->elapsedSeconds()),
            'elapsed_seconds' => $entry->elapsedSeconds(),
            'comment' => $entry->comment,
            'comment_pending' => $entry->comment_pending,
            'stop_reason' => $entry->stop_reason?->label(),
        ];
    }

    /** R-5: integer seconds rendered as `h m`. */
    public static function hours(int $seconds): string
    {
        $sign = $seconds < 0 ? '-' : '';
        $seconds = abs($seconds);

        return $sign.intdiv($seconds, 3600).'h '.str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT).'m';
    }
}
