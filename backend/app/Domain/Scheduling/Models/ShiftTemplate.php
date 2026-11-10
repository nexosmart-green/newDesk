<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Scheduling\Enums\WorkMode;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RF-06.01: "Mañana 9–13", "Completo 9–18". Times are local to the company.
 *
 * @property int $id
 * @property string $name
 * @property string $starts_at HH:MM:SS
 * @property string $ends_at HH:MM:SS
 * @property string $color
 * @property WorkMode $mode
 */
class ShiftTemplate extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    protected $fillable = ['company_id', 'name', 'starts_at', 'ends_at', 'color', 'mode'];

    protected function casts(): array
    {
        return ['mode' => WorkMode::class];
    }
}
