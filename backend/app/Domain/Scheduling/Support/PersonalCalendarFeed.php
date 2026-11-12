<?php

namespace App\Domain\Scheduling\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Queries\Calendar;
use Illuminate\Support\Carbon;

/**
 * RF-06.07: the person's own .ics — shifts, due tasks and holidays from two months back to six ahead.
 */
final class PersonalCalendarFeed
{
    private const MONTHS_BACK = 2;

    private const MONTHS_AHEAD = 6;

    public function __construct(private readonly Calendar $calendar) {}

    public function render(User $user): string
    {
        $timezone = $user->company->timezone;
        $data = $this->calendar->forPerson(
            $user,
            now($timezone)->subMonths(self::MONTHS_BACK)->toDateString(),
            now($timezone)->addMonths(self::MONTHS_AHEAD)->toDateString(),
        );

        $ical = new ICalendar("newDesk — {$user->fullName()}");
        foreach ($data['shifts'] as $shift) {
            $ical->timed("shift-{$shift['id']}@newdesk", 'Turno '.($shift['name'] ?? '')." ({$shift['mode_label']})", Carbon::parse($shift['start_utc']), Carbon::parse($shift['end_utc']));
        }
        foreach ($data['tasks_due'] as $task) {
            $ical->allDay("due-{$task['code']}@newdesk", "Vence {$task['code']}: {$task['title']}", $task['due_on']);
        }
        foreach ($data['holidays'] as $holiday) {
            $ical->allDay("holiday-{$holiday['id']}@newdesk", "Feriado: {$holiday['name']}", $holiday['date']);
        }

        return $ical->render();
    }
}
