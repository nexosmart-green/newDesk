<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\TimeTracking\Enums\StopReason;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Domain\TimeTracking\Support\TimerRules;
use App\Domain\TimeTracking\Support\UserTimerLock;
use Illuminate\Support\Facades\DB;

/**
 * RF-05.16: no comment (≥10 chars), no stop — the timer keeps running (CA-12.x).
 */
final class StopTimer
{
    public function __construct(
        private readonly TimerRules $rules,
        private readonly UserTimerLock $lock,
        private readonly CloseTimeEntry $closeEntry,
    ) {}

    public function __invoke(User $user, ?string $comment): TimeEntry
    {
        $this->rules->assertValidComment($comment);

        return $this->lock->run($user->id, fn () => DB::transaction(function () use ($user, $comment) {
            $active = TimeEntry::query()->forCompany($user->company_id)->where('user_id', $user->id)->running()->first()
                ?? throw new BusinessRuleViolation('No tenés un cronómetro corriendo.', 'no_active_timer', 404);

            $project = Project::query()->forCompany($user->company_id)->lockForUpdate()->findOrFail($active->project_id);
            $active = TimeEntry::query()->forCompany($user->company_id)->lockForUpdate()->findOrFail($active->id);

            return ($this->closeEntry)($active, $project, now(), StopReason::User, $comment);
        }));
    }
}
