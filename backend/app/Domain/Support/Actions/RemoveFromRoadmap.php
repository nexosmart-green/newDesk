<?php

namespace App\Domain\Support\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Models\RoadmapItem;
use Illuminate\Support\Facades\DB;

/** Takes a report off the user's own roadmap (D-066); someone else's is never touched. */
final class RemoveFromRoadmap
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $user, int $reportId): void
    {
        DB::transaction(function () use ($user, $reportId) {
            $removed = RoadmapItem::query()->where('user_id', $user->id)->where('report_id', $reportId)->delete();
            $report = $removed > 0 ? Report::query()->find($reportId) : null;
            if ($report !== null) {
                ($this->recordActivity)('report.roadmap_removed', $report, $user, null, null, $report->project_id);
            }
        });
    }
}
