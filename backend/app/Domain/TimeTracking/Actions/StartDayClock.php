<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Models\DayClock;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\TimerRules;
use App\Domain\TimeTracking\Support\UserTimerLock;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * RF-05.19 / CA-05.19a-b. Same user lock as the ▶ (StartTimer), so a day and a ▶ never both start;
 * the UNIQUE on `unsplit_user_id` settles two devices racing past the lock.
 */
final class StartDayClock
{
    public function __construct(
        private readonly TimerRules $rules,
        private readonly UserTimerLock $lock,
    ) {}

    public function __invoke(User $user): DayClock
    {
        if (! $user->hasPermission(Permission::TimeTrack)) {
            throw new BusinessRuleViolation('Tu rol no tiene permiso para cronometrar.', 'cannot_track', 403);
        }

        return $this->lock->run($user->id, function () use ($user) {
            $this->rules->assertNoPendingComment($user);
            $this->rules->assertNoDayClock($user);

            if (TimeEntry::query()->forCompany($user->company_id)->where('user_id', $user->id)->running()->exists()) {
                throw new BusinessRuleViolation('Tenés un cronómetro corriendo en una tarea: frenalo antes de iniciar el día.', 'timer_running');
            }

            try {
                return DayClock::query()->create([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'started_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                return DayClock::query()->forCompany($user->company_id)->where('user_id', $user->id)->unsplit()->firstOrFail();
            }
        });
    }
}
