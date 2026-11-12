<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Events\ShiftsChanged;
use App\Domain\Scheduling\Models\ShiftRecurrence;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Scheduling\Support\RecurrenceGeneration;
use App\Domain\Scheduling\Support\RecurringShiftGenerator;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * RF-06.03: one rule per person ("lun a vie, Completo, desde 01/12") and its first days, generated right
 * away up to the 8-week horizon. Each person is notified once for the whole operation (RF-06.08).
 */
final class CreateShiftRecurrences
{
    public function __construct(
        private readonly RecurringShiftGenerator $generator,
        private readonly RecordActivity $recordActivity,
    ) {}

    /**
     * @param  list<int>  $userIds
     * @param  list<int>  $weekdays  ISO 1..7
     * @return list<array{recurrence: ShiftRecurrence, generation: RecurrenceGeneration}>
     */
    public function __invoke(User $actor, array $userIds, ShiftTemplate $template, array $weekdays, string $startsOn, ?string $endsOn, bool $skipHolidays): array
    {
        $people = User::query()->where('company_id', $actor->company_id)->where('status', UserStatus::Active)
            ->whereKey($userIds)->orderBy('first_name')->orderBy('last_name')->get();
        if ($people->count() !== count(array_unique($userIds))) {
            throw new BusinessRuleViolation('Alguna persona no existe o está dada de baja.', 'member_inactive');
        }

        $weekdays = array_values(array_unique(array_map('intval', $weekdays)));
        sort($weekdays);
        $today = now($actor->company->timezone)->toDateString();

        $results = DB::transaction(function () use ($actor, $people, $template, $weekdays, $startsOn, $endsOn, $skipHolidays, $today) {
            $results = [];
            foreach ($people as $person) {
                $recurrence = ShiftRecurrence::query()->create([
                    'company_id' => $actor->company_id,
                    'user_id' => $person->id,
                    'shift_template_id' => $template->id,
                    'weekdays' => $weekdays,
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'skip_holidays' => $skipHolidays,
                    'created_by' => $actor->id,
                ])->setRelation('user', $person)->setRelation('template', $template);

                $generation = $this->generator->generate($recurrence, $today);

                ($this->recordActivity)('shift_recurrence.created', $recurrence, $actor, after: [
                    'person' => $person->fullName(),
                    'template' => $template->name,
                    'weekdays' => $weekdays,
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'skip_holidays' => $skipHolidays,
                    'generated' => count($generation->dates),
                ]);

                $results[] = ['recurrence' => $recurrence, 'generation' => $generation];
            }

            return $results;
        });

        $touched = [];
        foreach ($results as $result) {
            if ($result['generation']->dates !== []) {
                $touched[$result['recurrence']->user_id] = $result['generation']->dates;
            }
        }
        if ($touched !== []) {
            ShiftsChanged::dispatch($touched, $actor);
        }

        return $results;
    }
}
