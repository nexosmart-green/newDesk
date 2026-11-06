<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * RF-06.06: national holidays (AR) and company non-working days.
 *
 * @property int $id
 * @property Carbon $date
 * @property string $name
 * @property string $scope national|company
 */
class Holiday extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'date', 'name', 'scope'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
