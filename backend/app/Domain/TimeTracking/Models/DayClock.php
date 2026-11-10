<?php

namespace App\Domain\TimeTracking\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * RF-05.19 "Reloj del día": running while `ended_at` is NULL, pending until `split_at`.
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $split_at
 */
class DayClock extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'unsplit_user_id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'split_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /** The local midnight after the start, in the person's timezone: a forgotten day ends there (CA-05.19h). */
    public function cutoffAt(): Carbon
    {
        return $this->started_at->copy()->setTimezone($this->user->effectiveTimezone())->startOfDay()->addDay()->utc();
    }

    public function elapsedSeconds(?Carbon $now = null): int
    {
        $end = $this->ended_at ?? ($now ?? now())->min($this->cutoffAt());

        return max(0, (int) $this->started_at->diffInSeconds($end, false));
    }

    /** @param  Builder<DayClock>  $query */
    public function scopeUnsplit(Builder $query): Builder
    {
        return $query->whereNull('split_at');
    }
}
