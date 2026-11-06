<?php

namespace App\Domain\Monitoring\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * monitoreo's SSE `metrics` (MetricsEvent in the frontend). `full` is the whole metrics
 * section, sent only while the server is live (CA-13.06).
 */
final class MetricsCollected implements ShouldBroadcastNow
{
    /** @param  array<string, mixed>  $summary */
    public function __construct(
        public readonly int $companyId,
        public readonly int $serverId,
        public readonly array $summary,
        public readonly mixed $full = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('monitoring.'.$this->companyId);
    }

    public function broadcastAs(): string
    {
        return 'metrics';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $payload = ['server_id' => $this->serverId, 'metrics' => $this->summary];
        if ($this->full !== null) {
            $payload['full'] = $this->full;
        }

        return $payload;
    }
}
