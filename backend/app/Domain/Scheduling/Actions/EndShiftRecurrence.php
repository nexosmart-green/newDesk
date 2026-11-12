<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Events\ShiftsChanged;
use App\Domain\Scheduling\Models\ShiftRecurrence;
use App\Domain\Scheduling\Support\RecurringShiftGenerator;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * RF-06.03 "Finalizar": the rule stops on $endsOn (today or later). The days it generated after that go
 * away; the earlier ones stay. Moving the end further away lets it keep generating.
 */
final class EndShiftRecurrence
{
    public function __construct(
        private readonly RecurringShiftGenerator $generator,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @return array{recurrence: ShiftRecurrence, removed: int} */
    public function __invoke(User $actor, ShiftRecurrence $recurrence, string $endsOn): array
    {
        $today = now($actor->company->timezone)->toDateString();
        if ($endsOn < $today) {
            throw new BusinessRuleViolation('La fecha de fin no puede ser anterior a hoy.', 'recurrence_end_in_past');
        }
        if ($endsOn < $recurrence->starts_on->toDateString()) {
            throw new BusinessRuleViolation(
                'Este turno fijo empieza el '.$recurrence->starts_on->format('d/m/Y').': si ya no lo querés, borralo.',
                'recurrence_end_before_start',
            );
        }

        [$recurrence, $removed, $generated] = DB::transaction(function () use ($actor, $recurrence, $endsOn, $today) {
            $recurrence = ShiftRecurrence::query()->with(['user', 'template'])->lockForUpdate()->findOrFail($recurrence->id);
            $before = $recurrence->ends_on?->toDateString();

            $removed = $this->generator->removeAfter($recurrence, $endsOn);
            $recurrence->forceFill(['ends_on' => $endsOn])->save();
            $generated = $this->generator->generate($recurrence, $today)->dates;

            ($this->recordActivity)('shift_recurrence.ended', $recurrence, $actor,
                before: ['ends_on' => $before],
                after: ['ends_on' => $endsOn, 'removed' => count($removed), 'generated' => count($generated)],
            );

            return [$recurrence, $removed, $generated];
        });

        $touched = array_values(array_unique([...$removed, ...$generated]));
        if ($touched !== []) {
            ShiftsChanged::dispatch([$recurrence->user_id => $touched], $actor);
        }

        return ['recurrence' => $recurrence, 'removed' => count($removed)];
    }
}
