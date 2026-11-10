<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Enums\StatusKind;
use App\Domain\Tasks\Models\TaskStatus;
use Illuminate\Support\Facades\DB;

/**
 * RF-04.03 / RF-11.04: company-configurable statuses. Creates when $status is null.
 */
final class SaveTaskStatus
{
    public function __construct(
        private readonly EnsureStatusSetIsUsable $ensureUsable,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @param  array{name: string, color: string, kind: string}  $data */
    public function __invoke(User $actor, array $data, ?TaskStatus $status = null): TaskStatus
    {
        return DB::transaction(function () use ($actor, $data, $status) {
            $before = $status?->only('name', 'color', 'kind');

            if ($status === null) {
                $position = (int) TaskStatus::query()->forCompany($actor->company_id)->max('position') + 1;
                $status = new TaskStatus(['company_id' => $actor->company_id, 'position' => $position]);
            }

            $status->fill(['name' => $data['name'], 'color' => $data['color'], 'kind' => StatusKind::from($data['kind'])])->save();
            ($this->ensureUsable)($actor->company_id);

            ($this->recordActivity)($before === null ? 'task_status.created' : 'task_status.updated', $status, $actor,
                $before === null ? null : [...$before, 'kind' => $before['kind']->value],
                [...$status->only('name', 'color'), 'kind' => $status->kind->value]);

            return $status;
        });
    }
}
