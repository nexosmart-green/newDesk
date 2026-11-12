<?php

namespace App\Domain\Scheduling\Queries;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\People\Actions\ReplaceAvatar;
use App\Domain\People\Models\EmployeeProfile;
use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Scheduling\Models\ShiftAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * RF-06.05 "quién está hoy": shifts per day for the people the viewer may see. Leaves join in R2 (RF-08).
 * A constant number of queries whatever the team size.
 */
final class TeamCalendar
{
    public const MAX_DAYS = 31;

    /**
     * Who the viewer sees: everyone active with `shifts.manage`; otherwise themselves plus their active
     * direct reports. Empty = the viewer has nobody in charge and may not see the team calendar.
     *
     * @return Collection<int, User>
     */
    public function audienceOf(User $viewer): Collection
    {
        $people = User::query()->where('company_id', $viewer->company_id)->active()
            ->with('profile.jobTitle')->orderBy('first_name')->orderBy('last_name');

        if ($viewer->hasPermission(Permission::ShiftsManage)) {
            return $people->get();
        }

        $reports = EmployeeProfile::query()->forCompany($viewer->company_id)->where('reports_to_user_id', $viewer->id)->pluck('user_id');
        $people = $people->whereKey([$viewer->id, ...$reports])->get();

        return $people->contains(fn (User $person) => $person->id !== $viewer->id) ? $people : new Collection;
    }

    /**
     * @param  Collection<int, User>  $people
     * @return array<string, mixed>
     */
    public function __invoke(Collection $people, int $companyId, string $timezone, string $from, string $to): array
    {
        $shiftsByDate = ShiftAssignment::query()->forCompany($companyId)->whereIn('user_id', $people->modelKeys())
            ->whereBetween('date', [$from, $to])->with('template')->orderBy('starts_at')->get()
            ->groupBy(fn (ShiftAssignment $shift) => $shift->date->toDateString());
        $holidays = Holiday::query()->forCompany($companyId)->whereBetween('date', [$from, $to])->orderBy('id')->get()
            ->unique(fn (Holiday $holiday) => $holiday->date->toDateString())
            ->keyBy(fn (Holiday $holiday) => $holiday->date->toDateString());

        $days = [];
        for ($day = Carbon::parse($from); $day->toDateString() <= $to; $day->addDay()) {
            $date = $day->toDateString();
            $holiday = $holidays->get($date);
            $days[] = [
                'date' => $date,
                'holiday' => $holiday ? ['name' => $holiday->name, 'scope' => $holiday->scope] : null,
                'shifts' => ($shiftsByDate[$date] ?? collect())->map(fn (ShiftAssignment $shift) => [
                    'id' => $shift->id,
                    'user_id' => $shift->user_id,
                    'starts_at' => substr($shift->starts_at, 0, 5),
                    'ends_at' => substr($shift->ends_at, 0, 5),
                    'mode' => $shift->mode->value,
                    'template_name' => $shift->template?->name,
                    'color' => $shift->template?->color,
                ])->values()->all(),
            ];
        }

        return [
            'timezone' => $timezone,
            'people' => $people->map(fn (User $person) => [
                'id' => $person->id,
                'full_name' => $person->fullName(),
                'avatar' => ReplaceAvatar::urls($person->avatar_path),
                'job_title' => $person->profile?->jobTitle?->name,
            ])->values()->all(),
            'days' => $days,
        ];
    }
}
