<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;
use App\Domain\TimeTracking\Support\ManualEntryWindow;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.15: soft delete, hours return to the bag, the deletion stays in Actividad.
 */
final class DeleteTimeEntry
{
    public function __construct(
        private readonly ManualEntryWindow $window,
        private readonly EvaluateHourAlerts $evaluateAlerts,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(User $actor, TimeEntry $entry): void
    {
        if ($entry->isRunning()) {
            throw new BusinessRuleViolation('Detené el cronómetro antes de borrar el registro.', 'entry_running');
        }
        $this->window->assertAllows($actor, $entry->started_at);

        DB::transaction(function () use ($actor, $entry) {
            $project = Project::query()->forCompany($entry->company_id)->lockForUpdate()->findOrFail($entry->project_id);
            $entry = TimeEntry::query()->forCompany($entry->company_id)->lockForUpdate()->findOrFail($entry->id);

            ($this->recordActivity)('time.deleted', $entry, $actor, [
                'task' => $entry->task->code,
                'person' => $entry->user->fullName(),
                'seconds' => $entry->seconds,
                'started_at' => $entry->started_at->toIso8601String(),
                'comment' => $entry->comment,
            ], projectId: $project->id);

            $project->consumed_seconds -= (int) $entry->seconds;
            $project->save();
            $entry->delete();

            ($this->evaluateAlerts)($project, HourBag::of($project)->consumed());
        });
    }
}
