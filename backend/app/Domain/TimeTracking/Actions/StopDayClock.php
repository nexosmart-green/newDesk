<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Models\DayClock;
use Illuminate\Support\Carbon;

/**
 * RF-05.19. A day never runs past its local midnight (CA-05.19h): stopping late, or the minute job finding
 * it forgotten, ends it there. The conditional update makes a stop and the job racing harmless.
 */
final class StopDayClock
{
    public function __invoke(User $user): DayClock
    {
        $day = DayClock::query()->forCompany($user->company_id)->where('user_id', $user->id)
            ->whereNull('ended_at')->with('user.company')->first()
            ?? throw new BusinessRuleViolation('No tenés el reloj del día corriendo.', 'no_day_clock');

        $this->end($day, now());

        return $day->refresh();
    }

    /** Minute job (timers:cut): days still running after their midnight. */
    public function overdue(Carbon $now): int
    {
        $ended = 0;
        foreach (DayClock::query()->withoutGlobalScopes()->whereNull('ended_at')->with('user.company')->get() as $day) {
            if ($day->cutoffAt()->lessThanOrEqualTo($now)) {
                $ended += $this->end($day, $now);
            }
        }

        return $ended;
    }

    private function end(DayClock $day, Carbon $now): int
    {
        return DayClock::query()->withoutGlobalScopes()->whereKey($day->id)->whereNull('ended_at')
            ->update(['ended_at' => $now->copy()->min($day->cutoffAt()), 'updated_at' => $now]);
    }
}
