<?php

namespace App\Domain\Scheduling\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Contracts\NationalHolidaySource;
use App\Domain\Scheduling\Models\Holiday;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * P-26 / RF-06.06: brings a year's national holidays into a company. A day that already has a holiday (typed by
 * hand, imported before, or a company day off) is left as it is: importing again never duplicates nor overwrites.
 * Holidays an Admin deleted come back only if someone imports that year again.
 */
final class ImportNationalHolidays
{
    public function __construct(
        private readonly NationalHolidaySource $source,
        private readonly RecordActivity $recordActivity,
    ) {}

    /** @return array{added: int, already: int} */
    public function __invoke(int $companyId, int $year, ?User $actor = null): array
    {
        $holidays = $this->source->forYear($year);

        return DB::transaction(function () use ($companyId, $year, $holidays, $actor) {
            $taken = Holiday::query()->forCompany($companyId)->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])
                ->pluck('date')->map(fn ($date) => $date->toDateString())->flip();
            $added = 0;
            foreach ($holidays as $holiday) {
                if ($taken->has($holiday['date'])) {
                    continue;
                }
                $saved = Holiday::query()->create(['company_id' => $companyId, ...$holiday, 'scope' => 'national']);
                ($this->recordActivity)('holiday.imported', $saved, $actor, after: [...$holiday, 'scope' => 'national']);
                $taken->put($holiday['date'], true);
                $added++;
            }

            return ['added' => $added, 'already' => count($holidays) - $added];
        });
    }
}
