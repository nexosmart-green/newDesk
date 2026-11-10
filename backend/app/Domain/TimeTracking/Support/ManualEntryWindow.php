<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Carbon;

/**
 * RF-05.09 / P-06 / RF-11.03: manual hours and self-corrections up to N days back (default 7),
 * counted in the person's timezone. Beyond that, only `time.manage`. Changing N applies to new loads only (CA-11.03).
 */
final class ManualEntryWindow
{
    public function earliestAllowed(User $user): Carbon
    {
        $daysBack = (int) $user->company->setting('manual_entry_days_back');

        return now($user->effectiveTimezone())->startOfDay()->subDays($daysBack)->utc();
    }

    public function allows(User $user, Carbon $startedAt): bool
    {
        return $user->hasPermission(Permission::TimeManage) || $startedAt->greaterThanOrEqualTo($this->earliestAllowed($user));
    }

    public function assertAllows(User $user, Carbon $startedAt): void
    {
        if (! $this->allows($user, $startedAt)) {
            $days = $user->company->setting('manual_entry_days_back');
            throw new BusinessRuleViolation(
                "Solo se pueden cargar o corregir horas de los últimos {$days} días. Pedile a un administrador.",
                'outside_manual_window',
            );
        }
    }
}
