<?php

namespace App\Domain\StatusPage\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A degraded or down stretch of a component (D-039): the only source of uptime. A down one is an
 * incident (D-042). Open while `ended_at` is null.
 *
 * @property int $id
 * @property int $company_id
 * @property int $component_id
 * @property string $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at
 * @property string $detail
 */
class Period extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'status_periods';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Component, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /** @return HasMany<IncidentNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(IncidentNote::class);
    }
}
