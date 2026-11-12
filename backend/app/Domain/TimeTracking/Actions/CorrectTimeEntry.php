<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;
use App\Domain\TimeTracking\Support\ManualEntryWindow;
use App\Domain\TimeTracking\Support\TimerRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.15: Admin corrects anyone's entries; a person corrects their own within the window.
 * Growing an entry must fit in the bag (R-4); every change is audited with before/after (RNF-07).
 * Passing $startedAt/$endedAt null keeps the times; passing $task moves the entry there, also to another
 * project (R-7): the destination bag takes the whole time and the origin gives back what the entry had.
 */
final class CorrectTimeEntry
{
    public function __construct(
        private readonly TimerRules $rules,
        private readonly ManualEntryWindow $window,
        private readonly EvaluateHourAlerts $evaluateAlerts,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(User $actor, TimeEntry $entry, ?Carbon $startedAt, ?Carbon $endedAt, string $comment, ?Task $task = null): TimeEntry
    {
        if ($entry->isRunning()) {
            throw new BusinessRuleViolation('Detené el cronómetro antes de corregir el registro.', 'entry_running');
        }
        $this->rules->assertValidComment($comment);

        $startedAt ??= $entry->started_at;
        $endedAt ??= $entry->ended_at;
        if ($endedAt->lessThanOrEqualTo($startedAt) || $endedAt->isFuture()) {
            throw new BusinessRuleViolation('El rango horario no es válido.', 'invalid_range');
        }
        // Both the original and the new start must be inside the window: old entries cannot be moved into it.
        $this->window->assertAllows($actor, $entry->started_at);
        $this->window->assertAllows($actor, $startedAt);
        $task = $task?->id === $entry->task_id ? null : $task;

        return DB::transaction(function () use ($actor, $entry, $startedAt, $endedAt, $comment, $task) {
            // R-7: both projects in id order (as R-2/R-6), then the entry.
            $projects = Project::query()->forCompany($entry->company_id)
                ->whereKey(array_unique([$entry->project_id, $task->project_id ?? $entry->project_id]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $entry = TimeEntry::query()->forCompany($entry->company_id)->with(['task', 'user'])->lockForUpdate()->findOrFail($entry->id);
            $origin = $projects[$entry->project_id];
            $destination = $projects[$task->project_id ?? $entry->project_id];
            if ($task !== null) {
                $this->assertCanReceive($entry, $task->loadMissing('status'), $destination);
            }

            $before = $this->snapshot($entry);
            $seconds = (int) $startedAt->diffInSeconds($endedAt, true);
            // What the destination bag has to absorb: the growth when it stays, the whole time when it moves.
            $added = $origin->is($destination) ? $seconds - (int) $entry->seconds : $seconds;

            $bag = HourBag::of($destination);
            if ($added > 0 && $bag->hasCap() && $added > $bag->available()) {
                throw new BusinessRuleViolation("La corrección supera lo disponible en {$destination->name}.", 'manual_exceeds_bag');
            }

            $origin->consumed_seconds -= (int) $entry->seconds;
            $destination->consumed_seconds += $seconds;
            $entry->forceFill([
                'task_id' => $task->id ?? $entry->task_id,
                'project_id' => $destination->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'seconds' => $seconds,
                'comment' => trim($comment),
                'comment_pending' => false,
            ])->save();
            if ($task !== null) {
                $entry->setRelation('task', $task)->setRelation('project', $destination);
            }

            foreach ($projects as $project) {
                $project->save();
                ($this->recordActivity)('time.corrected', $entry, $actor, $before, $this->snapshot($entry), $project->id);
                ($this->evaluateAlerts)($project, HourBag::of($project)->consumed());
            }

            return $entry;
        });
    }

    /** Hours only go to an open task of an open project, assigned to whoever worked them. */
    private function assertCanReceive(TimeEntry $entry, Task $task, Project $destination): void
    {
        if (! $task->isAssignedTo($entry->user)) {
            throw new BusinessRuleViolation("{$task->code} no está asignada a {$entry->user->fullName()}.", 'task_not_assigned');
        }
        $this->rules->assertOpen($destination, $task);
    }

    /** @return array<string, mixed> */
    private function snapshot(TimeEntry $entry): array
    {
        return [
            'task' => $entry->task->code,
            'started_at' => $entry->started_at->toIso8601String(),
            'ended_at' => $entry->ended_at?->toIso8601String(),
            'seconds' => $entry->seconds,
            'comment' => $entry->comment,
        ];
    }
}
