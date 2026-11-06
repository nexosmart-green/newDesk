<?php

namespace App\Domain\Monitoring\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * monitoreo's SSE `snapshot`: a section has new data (SnapshotEvent in the frontend).
 */
final class SnapshotSaved implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $serverId,
        public readonly string $section,
        public readonly string $collectedAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('monitoring.'.$this->companyId);
    }

    public function broadcastAs(): string
    {
        return 'snapshot';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['server_id' => $this->serverId, 'section' => $this->section, 'collected_at' => $this->collectedAt];
    }
}
