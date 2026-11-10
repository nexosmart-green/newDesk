<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Enums\WorkMode;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's shift on one local date (RF-06.02). A shift ending before it starts crosses midnight.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property int|null $shift_template_id
 * @property string $starts_at
 * @property string $ends_at
 * @property WorkMode $mode
 */
class ShiftAssignment extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'mode' => WorkMode::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ShiftTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id')->withTrashed();
    }

    /** Start/end as absolute instants (QA-11: computed from the local date in the company timezone). @return array{0: Carbon, 1: Carbon} */
    public function interval(string $timezone): array
    {
        $date = $this->date->toDateString();
        $start = Carbon::parse("{$date} {$this->starts_at}", $timezone);
        $end = Carbon::parse("{$date} {$this->ends_at}", $timezone);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start->utc(), $end->utc()];
    }
}
