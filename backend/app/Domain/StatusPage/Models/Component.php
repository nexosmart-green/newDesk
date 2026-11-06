<?php

namespace App\Domain\StatusPage\Models;

use App\Domain\Monitoring\Models\Server;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\StatusPage\Status\ComponentState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing the status page checks for a project (RF-13.06, D-039): a site, an API, a PM2 app, a
 * Supervisor program or a backup timer. Its monitoring state lives on the row.
 *
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property string $kind
 * @property string $label
 * @property list<string> $parts client parts it affects (D-045)
 * @property int $position
 * @property string|null $url
 * @property int|null $server_id
 * @property string|null $target
 * @property string|null $target_owner
 * @property int|null $max_age_hours
 * @property int|null $slow_ms
 * @property string $status
 * @property CarbonImmutable|null $status_since
 * @property string $phrase
 * @property string $detail
 * @property int $bad_streak
 * @property CarbonImmutable|null $bad_since
 * @property CarbonImmutable|null $down_since
 * @property CarbonImmutable|null $last_checked_at
 * @property CarbonImmutable|null $last_ok_at
 * @property int|null $latency_ms
 * @property int|null $status_code
 * @property CarbonImmutable|null $ssl_expires_at
 * @property CarbonImmutable|null $created_at
 */
class Component extends Model
{
    use BelongsToCompany;

    public const HTTP = 'http';

    public const HEALTH = 'health';

    public const PM2 = 'pm2';

    public const SUPERVISOR = 'supervisor';

    public const TIMER = 'timer';

    public const KINDS = [self::HTTP, self::HEALTH, self::PM2, self::SUPERVISOR, self::TIMER];

    /** Kinds judged from an agent snapshot, and which section. */
    public const SECTION_OF = [self::PM2 => 'pm2', self::SUPERVISOR => 'supervisor', self::TIMER => 'cron'];

    protected $table = 'status_components';

    protected $guarded = [];

    protected $attributes = ['parts' => '', 'position' => 0, 'status' => ComponentState::UNKNOWN, 'phrase' => '', 'detail' => '', 'bad_streak' => 0];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'server_id' => 'integer',
            'max_age_hours' => 'integer',
            'slow_ms' => 'integer',
            'bad_streak' => 'integer',
            'latency_ms' => 'integer',
            'status_code' => 'integer',
            'status_since' => 'immutable_datetime',
            'bad_since' => 'immutable_datetime',
            'down_since' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'last_ok_at' => 'immutable_datetime',
            'ssl_expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** Stored as "web,app". */
    protected function parts(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null || $value === '' ? [] : explode(',', $value),
            set: fn (array $value) => implode(',', $value),
        );
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** How it names its process: a PM2 app bound to a user only matches in that user's daemon (D-089). */
    public function targetKey(): string
    {
        return $this->kind === self::PM2 && (string) $this->target_owner !== '' ? "{$this->target}@{$this->target_owner}" : (string) $this->target;
    }

    /** Components the client sees (through their parts); the rest are the team's. */
    public function hasParts(): bool
    {
        return $this->parts !== [];
    }

    public function state(): ComponentState
    {
        return new ComponentState(
            $this->status, $this->status_since, $this->phrase, $this->detail, $this->bad_streak, $this->bad_since,
            $this->down_since, $this->last_checked_at, $this->last_ok_at, $this->latency_ms, $this->status_code, $this->ssl_expires_at,
        );
    }

    /** @return array<string, mixed> the columns of a state */
    public static function stateColumns(ComponentState $state): array
    {
        return [
            'status' => $state->status,
            'status_since' => $state->statusSince,
            'phrase' => mb_substr($state->phrase, 0, 120),
            'detail' => mb_substr($state->detail, 0, 255),
            'bad_streak' => $state->badStreak,
            'bad_since' => $state->badSince,
            'down_since' => $state->downSince,
            'last_checked_at' => $state->lastCheckedAt,
            'last_ok_at' => $state->lastOkAt,
            'latency_ms' => $state->latencyMs,
            'status_code' => $state->statusCode,
            'ssl_expires_at' => $state->sslExpiresAt,
        ];
    }

    /** monitoreo's ComponentAdmin JSON (the team's view). Needs `server` loaded. @return array<string, mixed> */
    public function toAdminPayload(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'kind' => $this->kind,
            'label' => $this->label,
            'parts' => $this->parts,
            'position' => $this->position,
            'url' => $this->url,
            'server_id' => $this->server_id,
            'server_name' => $this->server?->name,
            'target' => $this->target,
            'target_owner' => $this->target_owner,
            'max_age_hours' => $this->max_age_hours,
            'slow_ms' => $this->slow_ms,
            'status' => $this->status,
            'status_since' => $this->status_since?->toIso8601ZuluString(),
            'detail' => $this->detail,
            'last_checked_at' => $this->last_checked_at?->toIso8601ZuluString(),
            'last_ok_at' => $this->last_ok_at?->toIso8601ZuluString(),
            'latency_ms' => $this->latency_ms,
            'status_code' => $this->status_code,
            'ssl_expires_at' => $this->ssl_expires_at?->toIso8601ZuluString(),
        ];
    }
}
