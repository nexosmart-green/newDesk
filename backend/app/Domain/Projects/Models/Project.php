<?php

namespace App\Domain\Projects\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Enums\ProjectType;
use App\Domain\Projects\Policies\ProjectPolicy;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\HourBag;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $client_id
 * @property string $name
 * @property string $code_prefix
 * @property string|null $description
 * @property ProjectType $type
 * @property ProjectStatus $status
 * @property int|null $leader_user_id
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int $consumed_seconds closed time entries (cache)
 * @property int $allocated_seconds Σ hour_allocations (cache)
 * @property int $hour_alert_level last consumption alert sent (RF-05.07)
 * @property int $task_sequence
 */
#[UseFactory(ProjectFactory::class)]
class Project extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use SoftDeletes;

    public const EDITABLE = ['client_id', 'name', 'code_prefix', 'description', 'type', 'leader_user_id', 'starts_on', 'ends_on'];

    protected $fillable = ['company_id', 'status', ...self::EDITABLE];

    private ?HourBag $hourBagSnapshot = null;

    protected $attributes = ['status' => ProjectStatus::Active->value, 'consumed_seconds' => 0, 'allocated_seconds' => 0, 'hour_alert_level' => 0, 'task_sequence' => 0];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'status' => ProjectStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'consumed_seconds' => 'integer',
            'allocated_seconds' => 'integer',
            'hour_alert_level' => 'integer',
            'task_sequence' => 'integer',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot('added_at', 'added_by');
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    /**
     * Read-only bag snapshot for presentation (task rows show "Sin horas disponibles" before the click), computed
     * once per loaded model: eager-loaded tasks of one project share this instance. Domain rules never use it —
     * they compute a fresh HourBag under the project lock.
     */
    public function hourBagSnapshot(): HourBag
    {
        return $this->hourBagSnapshot ??= HourBag::of($this);
    }

    /** CA-03.01: once task codes circulate, the prefix is frozen. */
    public function prefixIsLocked(): bool
    {
        return $this->task_sequence > 0;
    }

    /** Projects the viewer may see: all with `projects.manage`, otherwise only where they are a member. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return ProjectPolicy::seesAllProjects($viewer) ? $query : $query->withMember($viewer);
    }

    /** Projects where the user is a member, whatever their permissions (MCP "mis proyectos"). */
    public function scopeWithMember(Builder $query, User $user): Builder
    {
        return $query->whereHas('members', fn (Builder $members) => $members->whereKey($user->id));
    }

    /**
     * "Por agotarse" — the single definition: bag projects whose consumption (closed entries + running timers,
     * the same "Consumidas" as HourBag) reached `$percent` of the bag. A bag with nothing allocated counts once
     * anything was consumed (HourBag::percentConsumed() = 100).
     */
    public function scopeRunningOut(Builder $query, int $percent): Builder
    {
        [$consumed, $bindings] = self::consumedSecondsSql();

        return $query->where('projects.type', ProjectType::Bag)
            ->whereRaw("{$consumed} * 100 >= ? * projects.allocated_seconds", [...$bindings, $percent])
            ->whereRaw("{$consumed} > 0", $bindings);
    }

    /** Highest consumption percentage first (an empty bag with consumption counts as 100 %). */
    public function scopeMostConsumedFirst(Builder $query): Builder
    {
        [$consumed, $bindings] = self::consumedSecondsSql();

        return $query->orderByRaw("CASE WHEN projects.allocated_seconds > 0 THEN {$consumed} / projects.allocated_seconds ELSE 1 END DESC", $bindings)
            ->orderBy('projects.id');
    }

    /** Adds `running_seconds` (running timers up to now) so consumption is read without a query per project. */
    public function scopeWithRunningSeconds(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->qualifyColumn('*'));
        }

        return $query->selectSub(TimeEntry::runningSecondsSubquery('project_id', 'projects.id'), 'running_seconds');
    }

    /** `running_seconds` must come from scopeWithRunningSeconds(). */
    public function consumedWithRunningSeconds(): int
    {
        return $this->consumed_seconds + (int) $this->getAttribute('running_seconds');
    }

    /** @return array{0: string, 1: list<mixed>} SQL of "consumed_seconds + running timers" and its bindings */
    private static function consumedSecondsSql(): array
    {
        $running = TimeEntry::runningSecondsSubquery('project_id', 'projects.id');

        return ["(projects.consumed_seconds + ({$running->toSql()}))", $running->getBindings()];
    }
}
