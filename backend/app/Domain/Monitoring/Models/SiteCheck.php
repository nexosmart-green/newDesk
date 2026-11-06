<?php

namespace App\Domain\Monitoring\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The last HTTPS check of one public name of a server (RF-13.05, key: server_id + server_name).
 * Every check also lands in `monitoring_site_check_history` (30 days).
 *
 * @property int $server_id
 * @property string $server_name
 * @property int $company_id
 * @property string $url
 * @property int|null $status_code
 * @property int|null $latency_ms
 * @property bool $ok
 * @property string|null $error
 * @property CarbonImmutable|null $ssl_expires_at
 * @property CarbonImmutable $checked_at
 */
class SiteCheck extends Model
{
    use BelongsToCompany;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'monitoring_site_checks';

    protected $primaryKey = 'server_name';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'latency_ms' => 'integer',
            'ok' => 'boolean',
            'ssl_expires_at' => 'immutable_datetime',
            'checked_at' => 'immutable_datetime',
        ];
    }

    /** monitoreo's store.SiteCheck JSON; the days left are counted from $now, rounded down. @return array<string, mixed> */
    public function toPayload(CarbonInterface $now): array
    {
        return [
            'url' => $this->url,
            'server_name' => $this->server_name,
            'status_code' => $this->status_code,
            'latency_ms' => $this->latency_ms,
            'ok' => $this->ok,
            'error' => $this->error,
            'ssl_expires_at' => $this->ssl_expires_at?->toIso8601ZuluString(),
            'ssl_days_left' => $this->ssl_expires_at === null
                ? null
                : intdiv($this->ssl_expires_at->getTimestamp() - $now->getTimestamp(), 86400),
            'checked_at' => $this->checked_at->toIso8601ZuluString(),
        ];
    }
}
