<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/** RF-06.06 */
final class DeleteHoliday
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, Holiday $holiday): void
    {
        DB::transaction(function () use ($actor, $holiday) {
            ($this->recordActivity)('holiday.deleted', $holiday, $actor, before: [
                'date' => $holiday->date->toDateString(),
                'name' => $holiday->name,
                'scope' => $holiday->scope,
            ]);
            $holiday->delete();
        });
    }
}
