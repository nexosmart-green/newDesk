<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Events\ShiftsChanged;
use App\Domain\Scheduling\Models\ShiftRecurrence;
use App\Domain\Scheduling\Support\RecurringShiftGenerator;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * RF-06.03 "Borrar" = end the rule today and delete it. Its days up to today stay as plain shifts
 * (`recurrence_id` becomes null through the foreign key); the future ones go away.
 */
final class DeleteShiftRecurrence
{
    public function __construct(
        private readonly RecurringShiftGenerator $generator,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @return int days removed */
    public function __invoke(User $actor, ShiftRecurrence $recurrence): int
    {
        $today = now($actor->company->timezone)->toDateString();

        $removed = DB::transaction(function () use ($actor, $recurrence, $today) {
            $recurrence = ShiftRecurrence::query()->with('template')->lockForUpdate()->findOrFail($recurrence->id);
            $removed = $this->generator->removeAfter($recurrence, $today);

            ($this->recordActivity)('shift_recurrence.deleted', $recurrence, $actor, before: [
                'template' => $recurrence->template?->name,
                'weekdays' => $recurrence->weekdays,
                'starts_on' => $recurrence->starts_on->toDateString(),
                'ends_on' => $recurrence->ends_on?->toDateString(),
            ], after: ['removed' => count($removed)]);

            $recurrence->delete();

            return $removed;
        });

        if ($removed !== []) {
            ShiftsChanged::dispatch([$recurrence->user_id => $removed], $actor);
        }

        return count($removed);
    }
}
