<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Scheduling\Models\ShiftRecurrence;
use App\Domain\Scheduling\Support\RecurringShiftGenerator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * RF-06.03: daily, keeps every live rule generated up to today + 8 weeks (local to each company).
 * Idempotent: each rule resumes after `generated_until`. Rules of people who left or whose template was
 * deleted are left alone. No notification: rolling the horizon is not a change to anyone's schedule.
 */
final class ExtendShiftRecurrences
{
    public function __construct(private readonly RecurringShiftGenerator $generator) {}

    /** @return int days created */
    public function __invoke(): int
    {
        $created = 0;

        Company::query()->each(function (Company $company) use (&$created) {
            $today = now($company->timezone)->toDateString();
            $horizon = RecurringShiftGenerator::horizon($today);

            ShiftRecurrence::query()->forCompany($company->id)
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
                ->where(fn ($query) => $query->whereNull('generated_until')->orWhere('generated_until', '<', $horizon))
                ->whereHas('user', fn ($query) => $query->where('status', UserStatus::Active))
                ->whereHas('template', fn ($query) => $query->whereNull('deleted_at'))
                ->select('id')
                ->chunkById(200, function ($recurrences) use ($today, &$created) {
                    foreach ($recurrences as $recurrence) {
                        $created += $this->extendOne($recurrence->id, $today);
                    }
                });
        });

        return $created;
    }

    /**
     * One rule at a time, re-read under lock: ending or deleting it at the same moment (both lock it too)
     * must not be undone by a stale copy. A failure is reported and the night goes on with the other rules;
     * the rule retries tomorrow because its `generated_until` did not move.
     */
    private function extendOne(int $recurrenceId, string $today): int
    {
        try {
            return DB::transaction(function () use ($recurrenceId, $today) {
                $recurrence = ShiftRecurrence::query()->with(['user', 'template'])->lockForUpdate()->find($recurrenceId);

                return $recurrence === null ? 0 : count($this->generator->generate($recurrence, $today)->dates);
            });
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }
}
