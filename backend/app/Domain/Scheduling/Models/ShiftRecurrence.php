<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * RF-06.03: "lun a vie, Completo, desde 01/12" for one person. Days are generated up to a rolling
 * horizon (see RecurringShiftGenerator) and never overwrite a day the person already has.
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $shift_template_id
 * @property list<int> $weekdays ISO 1 (Monday) .. 7
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property bool $skip_holidays
 * @property Carbon|null $generated_until
 * @property int $created_by
 * @property Carbon $created_at
 */
class ShiftRecurrence extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'generated_until' => 'date',
            'skip_holidays' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Deleted templates stay visible ("plantilla eliminada") but stop generating days. */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id')->withTrashed();
    }

    /** @return HasMany<ShiftAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'recurrence_id');
    }
}
