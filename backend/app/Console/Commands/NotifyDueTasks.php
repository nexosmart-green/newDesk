<?php

namespace App\Console\Commands;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Notifications\TasksDueNotification;
use App\Domain\Tasks\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RF-09.03: hourly; for each company where it is 08:00 local, sends each assignee one summary of their
 * tasks due tomorrow and overdue. Idempotent per task/person/day (task_due_reminders).
 */
class NotifyDueTasks extends Command
{
    protected $signature = 'tasks:notify-due {--force : Ignore the 08:00 local-time window}';

    protected $description = 'Daily summary of tasks due tomorrow and overdue';

    private const LOCAL_HOUR = 8;

    public function handle(): int
    {
        Company::query()->each(function (Company $company) {
            $local = now($company->timezone);
            if (! $this->option('force') && $local->hour !== self::LOCAL_HOUR) {
                return;
            }

            $this->notifyCompany($company, $local->toDateString(), $local->copy()->addDay()->toDateString());
        });

        return self::SUCCESS;
    }

    private function notifyCompany(Company $company, string $today, string $tomorrow): void
    {
        $tasks = Task::query()->forCompany($company->id)->open()
            ->whereNotNull('due_on')->where('due_on', '<=', $tomorrow)
            ->with(['assignees' => fn ($q) => $q->where('users.status', UserStatus::Active)])
            ->get();

        $byPerson = [];
        foreach ($tasks as $task) {
            foreach ($task->assignees as $assignee) {
                $bucket = $task->due_on->toDateString() === $tomorrow ? 'tomorrow' : ($task->due_on->toDateString() < $today ? 'overdue' : null);
                if ($bucket === null || ! $this->markSent($task->id, $assignee->id, $today)) {
                    continue;
                }
                $byPerson[$assignee->id]['user'] = $assignee;
                $byPerson[$assignee->id][$bucket][] = "{$task->code} {$task->title}";
            }
        }

        foreach ($byPerson as $summary) {
            /** @var User $user */
            $user = $summary['user'];
            $user->notify(new TasksDueNotification($summary['tomorrow'] ?? [], $summary['overdue'] ?? []));
        }
    }

    private function markSent(int $taskId, int $userId, string $day): bool
    {
        return DB::table('task_due_reminders')->insertOrIgnore(['task_id' => $taskId, 'user_id' => $userId, 'sent_on' => $day]) === 1;
    }
}
