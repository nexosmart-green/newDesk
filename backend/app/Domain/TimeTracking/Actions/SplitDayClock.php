<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivityContext;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Enums\EntrySource;
use App\Domain\TimeTracking\Events\HourBagOverdrawn;
use App\Domain\TimeTracking\Models\DayClock;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;
use App\Domain\TimeTracking\Support\TimerRules;
use App\Domain\TimeTracking\Support\UserTimerLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RF-05.19 / CA-05.19d-i: a stopped day becomes one time entry per part. One transaction, locks in a fixed
 * order (the day, then every project by id, R-6) so it cannot deadlock with StartTimer or the minute cuts.
 * Parts may overdraw the bag: saved whole, the ▶ running there become uncapped and Admin + leader are told.
 */
final class SplitDayClock
{
    public function __construct(
        private readonly TimerRules $rules,
        private readonly UserTimerLock $lock,
        private readonly CreateTask $createTask,
        private readonly EvaluateHourAlerts $evaluateAlerts,
        private readonly RecordActivity $recordActivity,
        private readonly ActivityContext $context,
    ) {}

    /**
     * @param  list<array{from: int, to: int, project_id: int, task_id: int|null, new_task_title: string|null, comment: string}>  $parts
     *                                                                                                                                    offsets in seconds from the day's start
     * @return array{entries: list<TimeEntry>, overdrawn: list<array{project_id: int, name: string, seconds: int}>}
     */
    public function __invoke(User $user, DayClock $day, array $parts): array
    {
        [$result, $events] = $this->lock->run($user->id, fn () => DB::transaction(fn () => $this->split($user, $day, $parts)));

        foreach ($events as $event) {
            HourBagOverdrawn::dispatch(...$event);
        }

        return $result;
    }

    /** @return array{0: array{entries: list<TimeEntry>, overdrawn: list<array{project_id: int, name: string, seconds: int}>}, 1: list<array{0: Project, 1: User, 2: int}>} */
    private function split(User $user, DayClock $day, array $parts): array
    {
        $day = DayClock::query()->forCompany($user->company_id)->lockForUpdate()->findOrFail($day->id);
        if ($day->split_at !== null) {
            throw new BusinessRuleViolation('Ese día ya está repartido.', 'day_already_split');
        }
        if ($day->ended_at === null) {
            throw new BusinessRuleViolation('Frená el reloj del día antes de repartirlo.', 'day_not_stopped');
        }

        if ($parts === []) {
            $day->forceFill(['split_at' => now()])->save();
            ($this->recordActivity)('time.day_discarded', $day, $user, after: ['seconds' => $day->elapsedSeconds()]);

            return [['entries' => [], 'overdrawn' => []], []];
        }

        $this->assertWithinDay($day, $parts);
        $projects = Project::query()->forCompany($user->company_id)
            ->whereKey(array_unique(array_column($parts, 'project_id')))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $tasks = $this->resolveTasks($user, $parts, $projects);
        $this->assertNoOverlapWithMyHours($user, $day, $parts);

        $secondsByProject = [];
        foreach ($parts as $part) {
            $secondsByProject[$part['project_id']] = ($secondsByProject[$part['project_id']] ?? 0) + $part['to'] - $part['from'];
        }

        // R-6: measured before adding the parts; the ▶ running there keep their whole time from now on.
        $overdrawn = [];
        foreach ($secondsByProject as $projectId => $seconds) {
            $available = HourBag::of($projects[$projectId])->available();
            if ($available !== null && $seconds > $available) {
                $overdrawn[$projectId] = $seconds - max(0, $available);
                TimeEntry::query()->forCompany($user->company_id)->where('project_id', $projectId)->running()
                    ->lockForUpdate()->get()->each(fn (TimeEntry $entry) => $entry->forceFill(['uncapped' => true])->save());
            }
        }

        $entries = [];
        foreach ($parts as $index => $part) {
            $entry = TimeEntry::query()->create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'task_id' => $tasks[$index]->id,
                'project_id' => $part['project_id'],
                'started_at' => $day->started_at->copy()->addSeconds($part['from']),
                'ended_at' => $day->started_at->copy()->addSeconds($part['to']),
                'seconds' => $part['to'] - $part['from'],
                'source' => EntrySource::DayClock,
                'channel' => $this->context->source,
                'comment' => trim($part['comment']),
                'created_by' => $user->id,
            ]);
            ($this->recordActivity)('time.day_part_logged', $entry, $user, after: [
                'task' => $tasks[$index]->code,
                'seconds' => $entry->seconds,
                'day_clock_id' => $day->id,
            ], projectId: $part['project_id']);
            $entries[] = $entry;
        }

        $events = [];
        $belowZero = [];
        foreach ($secondsByProject as $projectId => $seconds) {
            $project = $projects[$projectId];
            $project->consumed_seconds += $seconds;
            $project->save();
            $bag = HourBag::of($project);
            ($this->evaluateAlerts)($project, $bag->consumed());
            if (isset($overdrawn[$projectId])) {
                // The notice says how much went over; the person sees where the bag ended (−X).
                $events[] = [$project, $user, $overdrawn[$projectId]];
                $belowZero[] = ['project_id' => $projectId, 'name' => $project->name, 'seconds' => max(0, -$bag->available())];
            }
        }

        $day->forceFill(['split_at' => now()])->save();

        return [['entries' => $entries, 'overdrawn' => $belowZero], $events];
    }

    private function assertWithinDay(DayClock $day, array $parts): void
    {
        $span = (int) $day->started_at->diffInSeconds($day->ended_at, false);
        $previousEnd = 0;
        foreach ($parts as $index => $part) {
            if ($part['from'] < $previousEnd || $part['to'] <= $part['from'] || $part['to'] > $span) {
                throw ValidationException::withMessages([
                    "parts.{$index}.from" => 'Esta parte se sale del día o se pisa con la anterior.',
                ]);
            }
            $previousEnd = $part['to'];
        }
    }

    /**
     * Each part's task: one of mine in that project and open, or a new one created now (assigned to me).
     * Created inside this transaction: a later failure leaves no orphan task.
     *
     * @return array<int, Task>
     */
    private function resolveTasks(User $user, array $parts, $projects): array
    {
        $tasks = [];
        foreach ($parts as $index => $part) {
            $project = $projects[$part['project_id']] ?? throw ValidationException::withMessages(["parts.{$index}.project_id" => 'Elegí un proyecto.']);

            try {
                if ($part['task_id'] === null) {
                    // CreateTask refuses a closed project (project_closed); it runs as a savepoint of this transaction.
                    $tasks[$index] = ($this->createTask)($user, $project, ['title' => trim((string) $part['new_task_title'])], [$user->id]);

                    continue;
                }

                $task = Task::query()->forCompany($user->company_id)->with('status')->find($part['task_id']);
                if ($task === null || $task->project_id !== $project->id) {
                    throw ValidationException::withMessages(["parts.{$index}.task_id" => 'Elegí una tarea de este proyecto.']);
                }
                $this->rules->assertCanTrack($user, $task);
                $this->rules->assertOpen($project, $task);
                $tasks[$index] = $task;
            } catch (BusinessRuleViolation $violation) {
                if ($violation->ruleCode === 'cannot_track') {
                    throw $violation;
                }
                throw ValidationException::withMessages([
                    "parts.{$index}.".($part['task_id'] === null ? 'project_id' : 'task_id') => $violation->getMessage(),
                ]);
            }
        }

        return $tasks;
    }

    /** CA-05.19e: never bill the same minute twice (a manual entry may sit inside the day). */
    private function assertNoOverlapWithMyHours(User $user, DayClock $day, array $parts): void
    {
        foreach ($parts as $index => $part) {
            $from = $day->started_at->copy()->addSeconds($part['from']);
            $to = $day->started_at->copy()->addSeconds($part['to']);
            $clash = TimeEntry::query()->forCompany($user->company_id)->where('user_id', $user->id)
                ->where('started_at', '<', $to)->where('ended_at', '>', $from)->with('task')->first();
            if ($clash !== null) {
                throw ValidationException::withMessages([
                    "parts.{$index}.from" => "Se pisa con horas que ya cargaste en {$clash->task->code}.",
                ]);
            }
        }
    }
}
