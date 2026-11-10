<?php

namespace App\Domain\Tasks\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Policies\ProjectPolicy;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\TimeTracking\Models\TimeEntry;
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
 * @property string|null $description markdown (GFM)
 * @property int $status_id
 * @property int|null $category_id
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

    /** Fields edited through UpdateTask. Status, assignees and labels have their own handling. */
    public const EDITABLE = ['title', 'description', 'priority', 'starts_on', 'due_on', 'estimate_seconds', 'category_id'];

    /** What TaskResource reads: eager load it wherever tasks are returned. */
    public const RESOURCE_RELATIONS = ['status', 'assignees', 'project', 'category', 'labels'];

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

    /** @return BelongsTo<TaskCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class);
    }

    /** @return BelongsToMany<TaskLabel, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'task_label', 'task_id', 'label_id')->orderBy('task_labels.name');
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

    /**
     * Due dates are calendar days, so they are compared as dates against the viewer's local day
     * (comparing instants would flag tasks due today as overdue in the evening west of UTC).
     */
    public function isOverdue(?string $localDate = null): bool
    {
        return $this->completed_at === null
            && $this->due_on !== null
            && $this->due_on->toDateString() < ($localDate ?? today()->toDateString());
    }

    /**
     * Seconds worked on the task, running timers counted up to now. Lists bring it in the same query
     * (scopeWithLoggedSeconds); a single task asks the database.
     */
    public function loggedSeconds(): int
    {
        if (array_key_exists('logged_seconds', $this->attributes)) {
            return (int) $this->attributes['logged_seconds'];
        }

        return (int) TimeEntry::query()->forCompany($this->company_id)
            ->where('task_id', $this->id)
            ->sum(TimeEntry::elapsedSecondsExpression());
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

    /** Adds `logged_seconds` as a subquery so lists never ask per row. */
    public function scopeWithLoggedSeconds(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->qualifyColumn('*'));
        }

        $sum = DB::table('time_entries')
            ->whereColumn('time_entries.task_id', $query->qualifyColumn('id'))
            ->whereNull('time_entries.deleted_at')
            ->selectRaw('COALESCE(SUM('.TimeEntry::elapsedSecondsExpression()->getValue(DB::connection()->getQueryGrammar()).'), 0)');

        return $query->selectSub($sum, 'logged_seconds');
    }

    /** Tasks of the projects the viewer may see: every project with `projects.manage`, otherwise theirs. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if (ProjectPolicy::seesAllProjects($viewer)) {
            return $query;
        }

        return $query->whereIn($query->qualifyColumn('project_id'), DB::table('project_members')->select('project_id')->where('user_id', $viewer->id));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('completed_at'));
    }
}
