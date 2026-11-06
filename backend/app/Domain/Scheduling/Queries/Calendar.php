<?php

namespace App\Domain\Scheduling\Queries;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Scheduling\Models\ShiftAssignment;
use App\Domain\Tasks\Models\Task;

/**
 * RF-06.04 "Mi calendario" (shifts, my tasks' due dates, holidays) and RF-06.05 team grid.
 * Leaves arrive with RF-08 (R2).
 */
final class Calendar
{
    /** @return array<string, mixed> */
    public function forPerson(User $user, string $from, string $to): array
    {
        $timezone = $user->company->timezone;

        return [
            'timezone' => $timezone,
            'shifts' => ShiftAssignment::query()->forCompany($user->company_id)->where('user_id', $user->id)
                ->whereBetween('date', [$from, $to])->with('template')->orderBy('date')->orderBy('starts_at')->get()
                ->map(fn (ShiftAssignment $shift) => $this->presentShift($shift, $timezone))->all(),
            'tasks_due' => Task::query()->forCompany($user->company_id)->open()
                ->whereHas('assignees', fn ($q) => $q->whereKey($user->id))
                ->whereBetween('due_on', [$from, $to])->orderBy('due_on')->get(['id', 'code', 'title', 'due_on'])
                ->map(fn (Task $task) => ['code' => $task->code, 'title' => $task->title, 'due_on' => $task->due_on->toDateString()])->all(),
            'holidays' => $this->holidays($user->company_id, $from, $to),
        ];
    }

    /** @return array<string, mixed> */
    public function forTeam(int $companyId, string $timezone, string $from, string $to): array
    {
        $shifts = ShiftAssignment::query()->forCompany($companyId)->whereBetween('date', [$from, $to])->with('template')->get()->groupBy('user_id');

        return [
            'timezone' => $timezone,
            'people' => User::query()->where('company_id', $companyId)->where('status', UserStatus::Active)
                ->orderBy('first_name')->orderBy('last_name')->get()
                ->map(fn (User $person) => [
                    'id' => $person->id,
                    'full_name' => $person->fullName(),
                    'shifts' => ($shifts[$person->id] ?? collect())->map(fn (ShiftAssignment $shift) => $this->presentShift($shift, $timezone))->values()->all(),
                ])->all(),
            'holidays' => $this->holidays($companyId, $from, $to),
        ];
    }

    /** @return array<string, mixed> */
    public function presentShift(ShiftAssignment $shift, string $timezone): array
    {
        [$start, $end] = $shift->interval($timezone);

        return [
            'id' => $shift->id,
            'date' => $shift->date->toDateString(),
            'name' => $shift->template?->name,
            'color' => $shift->template?->color,
            'starts_at' => substr($shift->starts_at, 0, 5),
            'ends_at' => substr($shift->ends_at, 0, 5),
            'mode' => $shift->mode->value,
            'mode_label' => $shift->mode->label(),
            'start_utc' => $start->toIso8601String(),
            'end_utc' => $end->toIso8601String(),
        ];
    }

    /** @return list<array<string, string>> */
    private function holidays(int $companyId, string $from, string $to): array
    {
        return Holiday::query()->forCompany($companyId)->whereBetween('date', [$from, $to])->orderBy('date')->get()
            ->map(fn (Holiday $holiday) => ['id' => $holiday->id, 'date' => $holiday->date->toDateString(), 'name' => $holiday->name, 'scope' => $holiday->scope])
            ->all();
    }
}
