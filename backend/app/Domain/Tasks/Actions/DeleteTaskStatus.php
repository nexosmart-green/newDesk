<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskStatus;
use App\Domain\Tasks\Support\EnsureStatusSetIsUsable;
use Illuminate\Support\Facades\DB;

final class DeleteTaskStatus
{
    public function __construct(
        private readonly EnsureStatusSetIsUsable $ensureUsable,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(User $actor, TaskStatus $status): void
    {
        DB::transaction(function () use ($actor, $status) {
            if (Task::query()->withTrashed()->forCompany($status->company_id)->where('status_id', $status->id)->exists()) {
                throw new BusinessRuleViolation(
                    "Hay tareas en «{$status->name}». Movelas a otro estado antes de borrarlo.",
                    'status_in_use',
                );
            }

            ($this->recordActivity)('task_status.deleted', $status, $actor, $status->only('name', 'color'));
            $status->delete();
            ($this->ensureUsable)($actor->company_id);
        });
    }
}
