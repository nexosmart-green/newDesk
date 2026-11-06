<?php

namespace App\Domain\People\Models;

use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * RF-01.11: hourly rate with validity. Money in integer cents (never floats).
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount_cents
 * @property string $currency
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 */
class HourlyRate extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date', 'amount_cents' => 'integer'];
    }

    /** Rate in force on a given day (CA-01.11). */
    public function scopeValidOn(Builder $query, Carbon $day): Builder
    {
        $date = $day->toDateString();

        return $query->where('valid_from', '<=', $date)
            ->where(fn (Builder $inner) => $inner->whereNull('valid_to')->orWhere('valid_to', '>=', $date));
    }
}
