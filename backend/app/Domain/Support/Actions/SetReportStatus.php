<?php

namespace App\Domain\Support\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Support\Events\ReportChanged;
use App\Domain\Support\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Anyone on the team moves a report: Recibido → En revisión → Resuelto (D-041). `resolved_at`
 * follows the resolved status and keeps the first time it got there.
 */
final class SetReportStatus
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(Report $report, string $status, User $actor): Report
    {
        DB::transaction(function () use ($report, $status, $actor) {
            $before = $report->status;
            $now = CarbonImmutable::now()->utc();
            $report->forceFill(['status' => $status, 'updated_at' => $now,
                'resolved_at' => $status === Report::RESOLVED ? ($report->resolved_at ?? $now) : null])->save();
            ($this->recordActivity)('report.status_changed', $report, $actor, ['status' => $before], ['status' => $status], $report->project_id);
        });
        ReportChanged::send($report, ReportChanged::REPORT);

        return $report;
    }
}
