<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\TimerRules;

/**
 * RF-04.02 "Crear e iniciar": creates the task assigned to its creator and starts their timer.
 *  - Another timer running without the closing comment → nothing is created (CA-04.02 asks first).
 *  - No hours left → the task is created, the timer is not, and the reason is returned (CA-04.02b).
 */
final class CreateAndStartTask
{
    public function __construct(
        private readonly CreateTask $createTask,
        private readonly StartTimer $startTimer,
        private readonly TimerRules $rules,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $assigneeIds
     * @return array{task: Task, entry: TimeEntry|null, timer_error: BusinessRuleViolation|null}
     */
    public function __invoke(User $user, Project $project, array $data, array $assigneeIds, ?string $previousComment): array
    {
        $this->rules->assertNoPendingComment($user);
        $running = TimeEntry::query()->runningFor($user)->exists();
        if ($running) {
            $this->rules->assertValidComment($previousComment, 'previous_comment_required');
        }

        $task = ($this->createTask)($user, $project, $data, array_values(array_unique([$user->id, ...$assigneeIds])));

        try {
            return ['task' => $task, 'entry' => ($this->startTimer)($user, $task, $previousComment), 'timer_error' => null];
        } catch (BusinessRuleViolation $timerError) {
            return ['task' => $task, 'entry' => null, 'timer_error' => $timerError];
        }
    }
}
