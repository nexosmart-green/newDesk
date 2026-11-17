<?php

namespace App\Domain\Support\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A report on a developer's own roadmap, at a position (D-064). Only its owner reads or changes it (D-066).
 *
 * @property int $company_id
 * @property int $user_id
 * @property int $report_id
 * @property int $position
 * @property CarbonImmutable $created_at
 */
class RoadmapItem extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $table = 'support_roadmap_items';

    protected $primaryKey = 'report_id';

    protected $guarded = [];

    protected $dateFormat = Report::DATE_FORMAT;

    protected function casts(): array
    {
        return ['position' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
