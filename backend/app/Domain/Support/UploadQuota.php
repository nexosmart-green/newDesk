<?php

namespace App\Domain\Support;

use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Models\ReportAttachment;
use App\Domain\Support\Models\ReportMessage;

/**
 * What a client keeps stored (D-090, CA-13.17): its reports' screenshots plus the images its users
 * sent in the chat; the team's images do not count, and what the retention purge removed no longer
 * does.
 */
final class UploadQuota
{
    public const BYTES = 200 << 20;

    public static function used(int $clientId): int
    {
        $screenshots = Report::query()->withoutGlobalScopes()->where('client_id', $clientId)->whereNotNull('screenshot_path')->sum('screenshot_size');
        $images = ReportAttachment::query()->withoutGlobalScopes()
            ->join('support_report_messages as messages', 'messages.id', '=', 'support_report_attachments.message_id')
            ->join('support_reports as reports', 'reports.id', '=', 'messages.report_id')
            ->where('reports.client_id', $clientId)->where('messages.author_kind', ReportMessage::CLIENT)
            ->sum('support_report_attachments.size');

        return (int) $screenshots + (int) $images;
    }

    /**
     * 413 when storing $incoming more bytes would take the client past the quota.
     *
     * ponytail: two uploads at the same instant can both fit just under the cap (as monitoreo); a per-client lock if that matters.
     *
     * @param  string  $without  how to send it anyway: "sin captura", "sin imágenes"
     */
    public static function ensureRoom(int $clientId, int $incoming, string $without): void
    {
        if ($incoming > 0 && self::used($clientId) + $incoming > self::BYTES) {
            throw new BusinessRuleViolation(sprintf('Llegaste al límite de imágenes guardadas (%d MB). Mandalo %s o escribinos por mail.',
                self::BYTES >> 20, $without), 'upload_quota', 413);
        }
    }
}
