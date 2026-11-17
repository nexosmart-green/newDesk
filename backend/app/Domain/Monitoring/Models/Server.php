<?php

namespace App\Domain\Monitoring\Models;

use App\Domain\Monitoring\Agent\Section;
use App\Domain\Shared\Casts\EncryptedFor;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A server running monitor-agent (RF-13.01).
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $ip
 * @property int|null $ssh_port
 * @property int $agent_port
 * @property string $token
 * @property string $tls_fingerprint
 * @property string|null $tls_spki_pin
 * @property bool $enabled
 * @property string $status
 * @property CarbonImmutable|null $last_seen_at
 * @property string|null $hostname
 * @property string|null $os
 * @property string|null $agent_version
 */
class Server extends Model
{
    use BelongsToCompany;

    public const STATUS_ONLINE = 'online';

    public const STATUS_AGENT_DOWN = 'agent_down';

    public const STATUS_DOWN = 'down';

    public const STATUS_UNKNOWN = 'unknown';

    protected $table = 'monitoring_servers';

    protected $fillable = [
        'company_id', 'name', 'ip', 'ssh_port', 'agent_port', 'token', 'tls_fingerprint', 'enabled',
    ];

    // CA-13.01: the token never leaves the backend.
    protected $hidden = ['token', 'tls_spki_pin'];

    protected $attributes = ['enabled' => true, 'status' => self::STATUS_UNKNOWN];

    protected function casts(): array
    {
        return [
            'token' => EncryptedFor::class,
            'ssh_port' => 'integer',
            'agent_port' => 'integer',
            'enabled' => 'boolean',
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        // The pin is derived from the fingerprint at this address: it is recomputed on the next contact.
        static::saving(function (Server $server) {
            if ($server->isDirty(['ip', 'agent_port', 'tls_fingerprint'])) {
                $server->tls_spki_pin = null;
            }
        });
    }

    /** @return HasMany<ServerSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(ServerSnapshot::class);
    }

    /** @return HasOne<ServerSnapshot, $this> */
    public function metricsSnapshot(): HasOne
    {
        return $this->hasOne(ServerSnapshot::class)->where('section', Section::METRICS);
    }

    /** The open ones: `withCount('firingAlerts')` is the summary's `active_alerts`. @return HasMany<Alert, $this> */
    public function firingAlerts(): HasMany
    {
        return $this->hasMany(Alert::class)->where('state', Alert::FIRING);
    }

    /**
     * Stores a pin derived from this address and fingerprint, unless someone changed them
     * meanwhile: then the pin would vouch for a certificate nobody entered.
     */
    public function rememberPin(string $pin): void
    {
        static::query()->whereKey($this->id)
            ->where('ip', $this->ip)
            ->where('agent_port', $this->agent_port)
            ->where('tls_fingerprint', $this->tls_fingerprint)
            ->toBase()
            ->update(['tls_spki_pin' => $pin]);
        $this->tls_spki_pin = $pin;
        $this->syncOriginalAttribute('tls_spki_pin');
    }

    public function sshPortOrDefault(): int
    {
        return $this->ssh_port !== null && $this->ssh_port > 0 ? $this->ssh_port : 22;
    }

    /** What the API shows: a disabled server is "disabled" whatever was last seen. */
    public function displayStatus(): string
    {
        return $this->enabled ? $this->status : 'disabled';
    }
}
