<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Tasks\Models\TaskLabel;
use Illuminate\Support\Facades\DB;

/**
 * Creates (when $label is null) or edits a task label. Colors are stored as lowercase `#rrggbb`.
 */
final class SaveTaskLabel
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, string $name, string $color, ?TaskLabel $label = null): TaskLabel
    {
        return DB::transaction(function () use ($actor, $name, $color, $label) {
            $before = $label?->only('name', 'color');
            $label ??= new TaskLabel(['company_id' => $actor->company_id]);
            $label->fill(['name' => $name, 'color' => mb_strtolower($color)])->save();

            ($this->recordActivity)($before === null ? 'task_label.created' : 'task_label.updated', $label, $actor, $before, $label->only('name', 'color'));

            return $label;
        });
    }
}
