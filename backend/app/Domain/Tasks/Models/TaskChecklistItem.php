<?php

namespace App\Domain\Tasks\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a task checklist (RF-04.08). Ordered by `position` inside its task.
 *
 * @property int $id
 * @property int $company_id
 * @property int $task_id
 * @property string $text
 * @property int $position
 * @property Carbon|null $done_at
 * @property int|null $done_by
 * @property int $created_by
 */
class TaskChecklistItem extends Model
{
    use BelongsToCompany;

    /** More than this stops being a checklist: it is a project. */
    public const MAX_PER_TASK = 100;

    public const MAX_TEXT_LENGTH = 500;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'done_at' => 'datetime'];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }
}
