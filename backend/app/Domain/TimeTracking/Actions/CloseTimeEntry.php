<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;
use Illuminate\Support\Carbon;

/**
 * The only place a running stretch becomes recorded hours. The caller holds the lock on $project.
 *
 * R-1: on a bag project the stretch is trimmed to what is left of the bag (allocated − already closed),
 * so closed hours never exceed the bag and simultaneous cuts end with Consumidas = Bolsa exactly.
 * RF-05.17: automatic cuts without a comment stay `comment_pending`.
 */
final class CloseTimeEntry
{
    public function __construct(
        private readonly EvaluateHourAlerts $evaluateAlerts,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(TimeEntry $entry, Project $project, Carbon $endAt, StopReason $reason, ?string $comment = null): TimeEntry
    {
        // Trust the row, not the (possibly stale) model: already closed → never count it twice (invariant 3).
        $entry->refresh();
        if ($entry->ended_at !== null) {
            return $entry;
        }

        $entry->loadMissing(['task', 'user']);
        // Signed difference: an end before the start (stale cut time) counts as 0, never as |diff|.
        $elapsed = max(0, (int) $entry->started_at->diffInSeconds($endAt, false));
        // R-6: a ▶ running when a day split overdrew the bag keeps its whole time.
        $seconds = $project->type->hasHourCap() && ! $entry->uncapped
            ? min($elapsed, max(0, $project->allocated_seconds - $project->consumed_seconds))
            : $elapsed;

        $comment = $comment !== null ? trim($comment) : null;

        $entry->forceFill([
            'ended_at' => $entry->started_at->copy()->addSeconds($seconds),
            'seconds' => $seconds,
            'stop_reason' => $reason,
            'comment' => $comment,
            'comment_pending' => ! TimeEntry::isValidComment($comment),
        ])->save();

        $project->consumed_seconds += $seconds;
        $project->save();

        // Automatic cuts have no human actor: they are logged as `system`.
        $actor = $reason->isAutomatic() ? null : $entry->user;
        ($this->recordActivity)('time.recorded', $entry, $actor, after: [
            'task' => $entry->task->code,
            'seconds' => $seconds,
            'trimmed_seconds' => $elapsed - $seconds,
            'stop_reason' => $reason->value,
        ], projectId: $project->id);

        ($this->evaluateAlerts)($project, HourBag::of($project, $endAt)->consumed());

        return $entry;
    }
}
