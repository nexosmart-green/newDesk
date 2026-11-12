<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * RF-06.06: a national holiday or company non-working day. Saving the same day and name twice keeps one.
 */
final class SaveHoliday
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  array{date: string, name: string, scope: string}  $data */
    public function __invoke(User $actor, array $data): Holiday
    {
        return DB::transaction(function () use ($actor, $data) {
            $holiday = Holiday::query()->firstOrCreate(
                ['company_id' => $actor->company_id, 'date' => $data['date'], 'name' => $data['name']],
                $data,
            );

            if ($holiday->wasRecentlyCreated) {
                ($this->recordActivity)('holiday.created', $holiday, $actor, after: $data);
            }

            return $holiday;
        });
    }
}
