<?php

namespace App\Domain\Support\Actions;

use App\Domain\Support\Models\Report;
use App\Domain\Support\Models\ReportAttachment;
use App\Domain\Support\ReportImage;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Retention of the problem reports (D-041, D-049, D-093), every company at once: screenshots and
 * chat images go after 180 days (the file first, then the reference; the text stays), the reports
 * after a year, and then the files under reports/ that no row names any more. A deleted client or
 * project cascades its rows but leaves its files (H15); a file younger than an hour may still be
 * waiting for its row (it is written before the live check).
 */
final class PurgeReports
{
    public const FILE_DAYS = 180;

    private const ORPHAN_GRACE_SECONDS = 3600;

    /** @return array<string, int> what went, by kind */
    public function __invoke(CarbonInterface $now): array
    {
        $now = $now->toImmutable()->utc();
        $cutoff = $now->subDays(self::FILE_DAYS)->format(Report::DATE_FORMAT);
        $disk = Storage::disk(ReportImage::DISK);
        $purged = ['screenshots' => 0, 'attachments' => 0, 'reports' => 0, 'orphan_files' => 0];

        Report::query()->withoutGlobalScopes()->whereNotNull('screenshot_path')->where('created_at', '<', $cutoff)
            ->chunkById(500, function ($reports) use ($disk, &$purged) {
                foreach ($reports as $report) {
                    $disk->delete($report->screenshot_path);
                    $report->timestamps = false;
                    $report->forceFill(['screenshot_path' => null, 'screenshot_type' => null])->save();
                    $purged['screenshots']++;
                }
            });
        ReportAttachment::query()->withoutGlobalScopes()->where('created_at', '<', $cutoff)
            ->chunkById(500, function ($attachments) use ($disk, &$purged) {
                foreach ($attachments as $attachment) {
                    $disk->delete($attachment->path);
                    $attachment->delete();
                    $purged['attachments']++;
                }
            });

        // Batched so a large backlog never holds long locks; messages and roadmap items cascade.
        $yearAgo = $now->subYear()->format(Report::DATE_FORMAT);
        do {
            $count = DB::table('support_reports')->where('created_at', '<', $yearAgo)->limit(5000)->delete();
            $purged['reports'] += $count;
        } while ($count === 5000);

        $named = DB::table('support_reports')->whereNotNull('screenshot_path')->pluck('screenshot_path')
            ->merge(DB::table('support_report_attachments')->pluck('path'))->flip();
        foreach ($disk->allFiles('reports') as $path) {
            if (! $named->has($path) && $now->getTimestamp() - $disk->lastModified($path) >= self::ORPHAN_GRACE_SECONDS) {
                $disk->delete($path);
                $purged['orphan_files']++;
            }
        }

        return $purged;
    }
}
