<?php

namespace App\Domain\Support\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Support\Events\ReportChanged;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Models\ReportMessage;
use App\Domain\Support\ReportImage;
use App\Domain\Support\UploadQuota;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A message in a report's chat (D-049), from its client or from the team, with up to 3 images. It
 * stamps the side that wrote; a client writing on a resolved report reopens it (in review). An
 * internal note (D-051) is the team's only: it leaves the client's marks and `updated_at` alone, so
 * the client sees no change, and its event reaches the team only (CA-13.18).
 */
final class PostReportMessage
{
    public const MAX_IMAGES = 3;

    /** @param  list<ReportImage>  $images  already checked */
    public function __invoke(Report $report, User $author, string $body, array $images, bool $internal): ReportMessage
    {
        $side = $author->isClient() ? ReportMessage::CLIENT : ReportMessage::TEAM;
        $internal = $internal && $side === ReportMessage::TEAM;
        if ($side === ReportMessage::CLIENT) {
            UploadQuota::ensureRoom($report->client_id, array_sum(array_map(fn (ReportImage $image) => $image->size(), $images)), 'sin imágenes');
        }
        $now = CarbonImmutable::now()->utc();
        $paths = array_map(fn (ReportImage $image) => $image->store($now), $images);

        $message = DB::transaction(function () use ($report, $author, $side, $body, $internal, $images, $paths, $now) {
            $message = ReportMessage::query()->create([
                'company_id' => $report->company_id, 'report_id' => $report->id, 'author_kind' => $side, 'user_id' => $author->id,
                'author' => mb_substr($author->email, 0, 255), 'body' => $body, 'internal' => $internal, 'created_at' => $now,
            ]);
            foreach ($images as $i => $image) {
                $message->attachments()->create(['company_id' => $report->company_id, 'path' => $paths[$i], 'content_type' => $image->contentType,
                    'size' => $image->size(), 'created_at' => $now]);
            }

            $stamped = Report::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($report->id);
            $stamped->timestamps = false;
            $stamped->forceFill(match (true) {
                $side === ReportMessage::CLIENT => ['last_client_message_at' => $now, 'client_seen_at' => $now, 'updated_at' => $now,
                    ...($stamped->status === Report::RESOLVED ? ['status' => Report::IN_REVIEW, 'resolved_at' => null] : [])],
                $internal => ['team_seen_at' => $now],
                default => ['last_team_message_at' => $now, 'team_seen_at' => $now, 'updated_at' => $now],
            })->save();

            return $message;
        });

        ReportChanged::send($report, ReportChanged::MESSAGE, teamOnly: $internal);

        return $message->load('attachments');
    }
}
