<?php

namespace App\Domain\Monitoring\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * What monitoreo's SSE `alert` carried to the browser: every alert opened or resolved, the
 * summarized ones too. Sent apart from AlertChanged so a Reverb failure only loses this.
 */
final class AlertBroadcast implements ShouldBroadcastNow
{
    public function __construct(public readonly AlertChanged $change) {}

    public function broadcastWhen(): bool
    {
        return $this->change->change === AlertChanged::OPENED || $this->change->change === AlertChanged::RESOLVED;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('monitoring.'.$this->change->companyId);
    }

    public function broadcastAs(): string
    {
        return 'alert';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->change->alert;
    }
}
