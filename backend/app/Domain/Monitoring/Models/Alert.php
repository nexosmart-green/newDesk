<?php

namespace App\Domain\Monitoring\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One alert of one server (RF-13.04): a rule firing for a target (a disk, a unit, a site…).
 * Opened, refreshed and resolved only by ApplyAlertResults.
 *
 * @property int $id
 * @property int $company_id
 * @property int $server_id
 * @property string $rule
 * @property string $target
 * @property string $severity
 * @property string $state
 * @property string $message
 * @property string $value
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $last_notified_at
 */
class Alert extends Model
{
    use BelongsToCompany;

    public const FIRING = 'firing';

    public const RESOLVED = 'resolved';

    public $timestamps = false;

    protected $table = 'monitoring_alerts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'last_notified_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** monitoreo's store.Alert JSON, for the API and the `alert` broadcast. Needs `server` loaded. @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'server_name' => $this->server->name,
            'rule' => $this->rule,
            'target' => $this->target,
            'severity' => $this->severity,
            'state' => $this->state,
            'message' => $this->message,
            'value' => $this->value,
            'first_seen_at' => $this->first_seen_at->toIso8601ZuluString(),
            'last_seen_at' => $this->last_seen_at->toIso8601ZuluString(),
            'resolved_at' => $this->resolved_at?->toIso8601ZuluString(),
            'last_notified_at' => $this->last_notified_at?->toIso8601ZuluString(),
        ];
    }
}
