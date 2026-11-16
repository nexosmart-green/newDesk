<?php

namespace App\Domain\Scheduling\Support;

use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Scheduling\Models\ShiftAssignment;
use App\Domain\Scheduling\Models\ShiftRecurrence;
use Illuminate\Support\Carbon;

/**
 * RF-06.03: materializes (and, when the rule ends, prunes) a rule's days from where it left off (`generated_until`) up to
 * `min(ends_on, today + 8 weeks)`, all in the company's local dates. A day the person already has
 * (by hand or by another rule) is never overwritten, and holidays are skipped when the rule says so.
 *
 * Callers run it inside a transaction with `user` and `template` eager loaded.
 */
final class RecurringShiftGenerator
{
    public const HORIZON_WEEKS = 8;

    public static function horizon(string $today): string
    {
        return Carbon::parse($today)->addWeeks(self::HORIZON_WEEKS)->toDateString();
    }

    public function generate(ShiftRecurrence $recurrence, string $today): RecurrenceGeneration
    {
        $template = $recurrence->template;
        if ($template === null || $template->trashed() || ! $recurrence->user->isActive()) {
            return new RecurrenceGeneration;
        }

        $from = max($recurrence->starts_on->toDateString(), $today, $recurrence->generated_until?->copy()->addDay()->toDateString() ?? '');
        $until = min($recurrence->ends_on?->toDateString() ?? '9999-12-31', self::horizon($today));
        if ($from > $until) {
            return new RecurrenceGeneration;
        }

        $holidays = $recurrence->skip_holidays ? $this->holidayDates($recurrence->company_id, $from, $until) : [];
        $taken = array_flip(ShiftAssignment::query()->forCompany($recurrence->company_id)->where('user_id', $recurrence->user_id)
            ->whereBetween('date', [$from, $until])->toBase()->pluck('date')->map(fn ($date) => substr((string) $date, 0, 10))->all());

        $created = [];
        $skipped = [];
        $rows = [];
        $now = now();
        foreach ($this->matchingDates($recurrence->weekdays, $from, $until) as $date) {
            if (isset($holidays[$date])) {
                $skipped[] = ['date' => $date, 'reason' => RecurrenceGeneration::SKIPPED_HOLIDAY];
            } elseif (isset($taken[$date])) {
                $skipped[] = ['date' => $date, 'reason' => RecurrenceGeneration::SKIPPED_TAKEN];
            } else {
                $created[] = $date;
                $rows[] = [
                    'company_id' => $recurrence->company_id,
                    'user_id' => $recurrence->user_id,
                    'date' => $date,
                    'shift_template_id' => $template->id,
                    'recurrence_id' => $recurrence->id,
                    'starts_at' => $template->starts_at,
                    'ends_at' => $template->ends_at,
                    'mode' => $template->mode->value,
                    'assigned_by' => $recurrence->created_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            // A shift assigned by hand between reading `$taken` and this insert wins (unique user+date+start):
            // the rule's row for that day is dropped instead of failing the whole generation.
            ShiftAssignment::query()->insertOrIgnore($rows);
        }
        $recurrence->forceFill(['generated_until' => $until])->save();

        return new RecurrenceGeneration($created, $skipped);
    }

    /**
     * Ending a rule: its generated days after $lastDay go away; earlier ones (worked or not) stay.
     *
     * @return list<string> local days removed
     */
    public function removeAfter(ShiftRecurrence $recurrence, string $lastDay): array
    {
        $future = ShiftAssignment::query()->forCompany($recurrence->company_id)
            ->where('recurrence_id', $recurrence->id)->where('date', '>', $lastDay);
        $dates = (clone $future)->toBase()->pluck('date')->map(fn ($date) => substr((string) $date, 0, 10))->all();
        $future->delete();

        if ($recurrence->generated_until !== null && $recurrence->generated_until->toDateString() > $lastDay) {
            $recurrence->forceFill(['generated_until' => $lastDay])->save();
        }

        return $dates;
    }

    /**
     * @param  list<int>  $weekdays
     * @return list<string>
     */
    private function matchingDates(array $weekdays, string $from, string $until): array
    {
        $dates = [];
        for ($day = Carbon::parse($from); $day->toDateString() <= $until; $day->addDay()) {
            if (in_array($day->dayOfWeekIso, $weekdays, true)) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }

    /** @return array<string, true> national and company non-working days */
    private function holidayDates(int $companyId, string $from, string $until): array
    {
        return array_fill_keys(
            Holiday::query()->forCompany($companyId)->whereBetween('date', [$from, $until])->toBase()->pluck('date')
                ->map(fn ($date) => substr((string) $date, 0, 10))->all(),
            true,
        );
    }
}
