<?php

namespace App\Domain\Support\Events;

use App\Domain\Support\Models\Report;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * monitoreo's SSE `report` (a report came in or changed) and `report.message` (a new chat message,
 * D-049): only ids travel, and the browser asks again with its own permissions. The team hears it
 * on its company channel, the report's client on its own; an internal note reaches the team only
 * (D-051, CA-13.18). Notices to the team (mail, Discord) hang from ReportSubmitted, not from this.
 */
final class ReportChanged implements ShouldBroadcastNow
{
    public const REPORT = 'report';

    public const MESSAGE = 'report.message';

    public function __construct(
        public readonly int $companyId,
        public readonly int $clientId,
        public readonly int $reportId,
        public readonly string $projectSlug,
        public readonly string $name,
        public readonly bool $teamOnly = false,
    ) {}

    /** Realtime is best effort: Reverb being down never fails the write that just happened. */
    public static function send(Report $report, string $name, bool $teamOnly = false): void
    {
        try {
            $slug = (string) $report->loadMissing('project')->project->slug;
            event(new self($report->company_id, $report->client_id, $report->id, $slug, $name, $teamOnly));
        } catch (Throwable $e) {
            Log::warning('support: broadcast failed', ['report' => $report->id, 'event' => $name, 'error' => $e->getMessage()]);
        }
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("monitoring.{$this->companyId}")];
        if (! $this->teamOnly) {
            $channels[] = new PrivateChannel("portal.client.{$this->clientId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return $this->name;
    }

    /** @return array{report_id: int, project_slug: string} */
    public function broadcastWith(): array
    {
        return ['report_id' => $this->reportId, 'project_slug' => $this->projectSlug];
    }
}
