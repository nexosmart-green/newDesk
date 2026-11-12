<?php

namespace App\Domain\TimeTracking\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Activity\ActivitySource;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Enums\EntrySource;
use App\Domain\TimeTracking\Enums\StopReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A worked stretch. `ended_at` NULL means it is the person's running timer (RF-05.03: lives on the server).
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $task_id
 * @property int $project_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int|null $seconds
 * @property EntrySource $source
 * @property ActivitySource $channel
 * @property string|null $comment
 * @property bool $comment_pending
 * @property StopReason|null $stop_reason
 */
class TimeEntry extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    /** RF-05.16: "¿qué hiciste?" — minimum length, web and MCP alike. */
    public const MIN_COMMENT_LENGTH = 10;

    protected $guarded = ['id', 'active_user_id'];

    protected $attributes = ['comment_pending' => false, 'uncapped' => false];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'seconds' => 'integer',
            'source' => EntrySource::class,
            'channel' => ActivitySource::class,
            'stop_reason' => StopReason::class,
            'comment_pending' => 'boolean',
            'uncapped' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /** Seconds counted so far: stored when closed, computed against "now" while running (R-5: integers). */
    public function elapsedSeconds(?Carbon $now = null): int
    {
        if ($this->seconds !== null) {
            return $this->seconds;
        }

        return max(0, (int) $this->started_at->diffInSeconds($now ?? now(), true));
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('ended_at'));
    }

    /** Entries that started on the local days $from..$to (Y-m-d, inclusive, either may be null) of $timezone. */
    public function scopeStartedOnLocalDays(Builder $query, ?string $from, ?string $to, string $timezone): Builder
    {
        $column = $query->qualifyColumn('started_at');

        return $query
            ->when($from, fn (Builder $q, string $day) => $q->where($column, '>=', Carbon::createFromFormat('!Y-m-d', $day, $timezone)->utc()))
            ->when($to, fn (Builder $q, string $day) => $q->where($column, '<', Carbon::createFromFormat('!Y-m-d', $day, $timezone)->addDay()->utc()));
    }

    public static function isValidComment(?string $comment): bool
    {
        return $comment !== null && mb_strlen(trim($comment)) >= self::MIN_COMMENT_LENGTH;
    }
}
