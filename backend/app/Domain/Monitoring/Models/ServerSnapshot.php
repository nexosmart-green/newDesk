<?php

namespace App\Domain\Monitoring\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The latest answer of one agent section (key: server_id + section). `data` and `warnings`
 * hold the agent's raw JSON; a failed fetch only sets `error` and keeps the last good data.
 *
 * @property int $server_id
 * @property string $section
 * @property int $company_id
 * @property CarbonImmutable|null $collected_at
 * @property CarbonImmutable $fetched_at
 * @property string|null $agent_version
 * @property string|null $data
 * @property string|null $warnings
 * @property string|null $error
 */
class ServerSnapshot extends Model
{
    use BelongsToCompany;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'monitoring_server_snapshots';

    protected $primaryKey = 'section';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['collected_at' => 'immutable_datetime', 'fetched_at' => 'immutable_datetime'];
    }

    /** The JSON shape monitoreo returns for one section (view.SectionSnapshot). @return array<string, mixed> */
    public function toSection(): array
    {
        return [
            'collected_at' => $this->collected_at?->toJSON(),
            'fetched_at' => $this->fetched_at->toJSON(),
            'agent_version' => $this->agent_version,
            'data' => $this->data === null || $this->data === '' ? null : json_decode($this->data),
            'warnings' => $this->warnings === null || $this->warnings === '' ? null : json_decode($this->warnings),
            'error' => $this->error,
        ];
    }
}
