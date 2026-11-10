<?php

namespace App\Domain\Tasks\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Tasks\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property int $number
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property int $status_id
 * @property TaskPriority $priority
 * @property Carbon|null $starts_on
 * @property Carbon|null $due_on
 * @property int|null $estimate_seconds
 * @property Carbon|null $completed_at
 * @property int $created_by
 */
class Task extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    /** Fields edited through UpdateTask. Status and assignees have their own actions. */
    public const EDITABLE = ['title', 'description', 'priority', 'starts_on', 'due_on', 'estimate_seconds'];

    protected $guarded = ['id'];

    protected $attributes = ['priority' => 'medium'];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'starts_on' => 'date',
            'due_on' => 'date',
            'completed_at' => 'datetime',
            'estimate_seconds' => 'integer',
            'number' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<TaskStatus, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TaskStatus::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this> */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')->withPivot('assigned_at');
    }

    /** @return HasMany<TaskComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees()->whereKey($user->id)->exists();
    }

    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->completed_at === null && $this->due_on !== null && $this->due_on->lt(($today ?? today())->startOfDay());
    }

    /** By current code or by a code it had before moving projects (RF-04.12). Case-insensitive. */
    public function scopeWithCode(Builder $query, string $code): Builder
    {
        $code = mb_strtolower(trim($code));

        return $query->where(fn (Builder $inner) => $inner
            ->where($inner->qualifyColumn('code'), $code)
            ->orWhereIn($inner->qualifyColumn('id'), DB::table('task_code_aliases')->select('task_id')
                ->where('code', $code)->whereColumn('task_code_aliases.company_id', 'tasks.company_id')));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('completed_at'));
    }
}
