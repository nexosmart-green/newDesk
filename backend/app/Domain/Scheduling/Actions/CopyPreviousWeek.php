<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Events\ShiftsChanged;
use App\Domain\Scheduling\Models\ShiftAssignment;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * RF-06.02 "copiar semana anterior": the week starting on $monday becomes a copy of the previous one,
 * for every person who had shifts in it.
 */
final class CopyPreviousWeek
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, string $monday): int
    {
        $target = Carbon::parse($monday)->startOfWeek(Carbon::MONDAY);
        $source = $target->copy()->subWeek();

        $previous = ShiftAssignment::query()->forCompany($actor->company_id)
            ->whereBetween('date', [$source->toDateString(), $source->copy()->addDays(6)->toDateString()])
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->get();

        $touched = DB::transaction(function () use ($actor, $target, $previous) {
            $userIds = $previous->pluck('user_id')->unique()->all();
            ShiftAssignment::query()->forCompany($actor->company_id)->whereIn('user_id', $userIds)
                ->whereBetween('date', [$target->toDateString(), $target->copy()->addDays(6)->toDateString()])
                ->delete();

            $touched = [];
            foreach ($previous as $shift) {
                $date = $shift->date->copy()->addWeek()->toDateString();
                ShiftAssignment::query()->create([
                    ...$shift->only('company_id', 'user_id', 'shift_template_id', 'starts_at', 'ends_at', 'mode'),
                    'date' => $date,
                    'assigned_by' => $actor->id,
                ]);
                $touched[$shift->user_id][] = $date;
            }

            ($this->recordActivity)('shifts.week_copied', $actor, $actor, after: ['week' => $target->toDateString(), 'shifts' => $previous->count()]);

            return $touched;
        });

        if ($touched !== []) {
            ShiftsChanged::dispatch($touched, $actor);
        }

        return $previous->count();
    }
}
