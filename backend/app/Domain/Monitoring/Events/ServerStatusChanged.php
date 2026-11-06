<?php

namespace App\Domain\Monitoring\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * monitoreo's SSE `server.status`, same payload (StatusEvent in the frontend).
 */
final class ServerStatusChanged implements ShouldBroadcastNow
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $serverId,
        public readonly string $status,
        public readonly ?string $lastSeenAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('monitoring.'.$this->companyId);
    }

    public function broadcastAs(): string
    {
        return 'server.status';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['server_id' => $this->serverId, 'status' => $this->status, 'last_seen_at' => $this->lastSeenAt];
    }
}
