<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivityContext;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Enums\EntrySource;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;
use App\Domain\TimeTracking\Support\ManualEntryWindow;
use App\Domain\TimeTracking\Support\TimerRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.09 "olvidé el timer": a closed stretch with a mandatory note.
 * R-4: if it does not fit in the bag it is rejected whole, never silently trimmed.
 */
final class LogManualTime
{
    public function __construct(
        private readonly TimerRules $rules,
        private readonly ManualEntryWindow $window,
        private readonly EvaluateHourAlerts $evaluateAlerts,
        private readonly RecordActivity $recordActivity,
        private readonly ActivityContext $context,
    ) {}

    public function __invoke(User $user, Task $task, Carbon $startedAt, Carbon $endedAt, ?string $comment): TimeEntry
    {
        $this->rules->assertCanTrack($user, $task);
        $this->rules->assertValidComment($comment);
        $this->assertValidRange($startedAt, $endedAt);
        $this->window->assertAllows($user, $startedAt);

        return DB::transaction(function () use ($user, $task, $startedAt, $endedAt, $comment) {
            $project = Project::query()->forCompany($user->company_id)->lockForUpdate()->findOrFail($task->project_id);
            $task = Task::query()->forCompany($user->company_id)->with('status')->findOrFail($task->id);
            $this->rules->assertTrackable($project, $task);

            $seconds = (int) $startedAt->diffInSeconds($endedAt, true);
            $bag = HourBag::of($project);
            if ($bag->hasCap() && $seconds > $bag->available()) {
                throw new BusinessRuleViolation(
                    "Esas horas superan lo disponible en {$project->name}. Pedí una ampliación o cargá menos tiempo.",
                    'manual_exceeds_bag',
                    context: ['available_seconds' => $bag->available()],
                );
            }

            $entry = TimeEntry::query()->create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'task_id' => $task->id,
                'project_id' => $project->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'seconds' => $seconds,
                'source' => EntrySource::Manual,
                'channel' => $this->context->source,
                'comment' => trim((string) $comment),
                'created_by' => $user->id,
            ]);

            $project->consumed_seconds += $seconds;
            $project->save();

            ($this->recordActivity)('time.manual_logged', $entry, $user, after: [
                'task' => $task->code, 'seconds' => $seconds, 'started_at' => $startedAt->toIso8601String(),
            ], projectId: $project->id);
            ($this->evaluateAlerts)($project, HourBag::of($project)->consumed());

            return $entry;
        });
    }

    private function assertValidRange(Carbon $startedAt, Carbon $endedAt): void
    {
        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            throw new BusinessRuleViolation('La hora de fin tiene que ser posterior a la de inicio.', 'invalid_range');
        }
        if ($endedAt->isFuture()) {
            throw new BusinessRuleViolation('No se pueden cargar horas futuras.', 'invalid_range');
        }
    }
}
