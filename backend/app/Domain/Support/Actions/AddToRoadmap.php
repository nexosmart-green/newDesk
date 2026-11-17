<?php

namespace App\Domain\Support\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Support\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Appends a report at the end of the user's own roadmap (D-064, D-066); one already there stays where it is. */
final class AddToRoadmap
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $user, Report $report): void
    {
        DB::transaction(function () use ($user, $report) {
            // One statement, so two adds at once never take the same position.
            DB::insert('INSERT IGNORE INTO support_roadmap_items (company_id, user_id, report_id, position, created_at)
                SELECT ?, ?, ?, COALESCE(MAX(position), 0) + 1, ? FROM support_roadmap_items WHERE user_id = ?',
                [$report->company_id, $user->id, $report->id, CarbonImmutable::now()->utc()->format(Report::DATE_FORMAT), $user->id]);
            ($this->recordActivity)('report.roadmap_added', $report, $user, null, null, $report->project_id);
        });
    }
}
