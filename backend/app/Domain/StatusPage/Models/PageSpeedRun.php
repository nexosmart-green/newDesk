<?php

namespace App\Domain\StatusPage\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One PageSpeed Insights measurement of a web component (D-043). A failed one keeps its error and
 * waits a week; it never replaces the last good one.
 *
 * @property int $id
 * @property int $company_id
 * @property int $component_id
 * @property string $strategy mobile | desktop
 * @property int|null $score
 * @property int|null $lcp_ms
 * @property float|null $cls
 * @property int|null $inp_ms
 * @property int|null $fcp_ms
 * @property int|null $tbt_ms
 * @property bool $field real users' data (CrUX) instead of the lab
 * @property string|null $error
 * @property CarbonImmutable $measured_at
 */
class PageSpeedRun extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'status_pagespeed_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'integer', 'lcp_ms' => 'integer', 'cls' => 'float', 'inp_ms' => 'integer', 'fcp_ms' => 'integer',
            'tbt_ms' => 'integer', 'field' => 'boolean', 'measured_at' => 'immutable_datetime',
        ];
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'measured_at' => $this->measured_at->toIso8601ZuluString(),
            'score' => $this->score,
            'lcp_ms' => $this->lcp_ms,
            'cls' => $this->cls,
            'inp_ms' => $this->inp_ms,
            'fcp_ms' => $this->fcp_ms,
            'tbt_ms' => $this->tbt_ms,
            'field' => $this->field,
        ];
    }
}
