<?php

namespace App\Domain\Monitoring\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * An alert opened, turned critical, resolved, or a flood of openings summarized (monitoreo
 * Engine.OnChange). `alert` is monitoreo's store.Alert JSON; a summary's has no id.
 *
 * The browser gets what monitoreo's SSE `alert` carried: every alert opened or resolved, also the
 * summarized ones. Notices (Discord, RF-13.09) listen to this event: they post `summary` instead
 * of each `summarized` opening (D-107, CA-13.11).
 */
final class AlertChanged implements ShouldBroadcastNow
{
    public const OPENED = 'opened';

    /** A warning that turned critical. */
    public const ESCALATED = 'escalated';

    public const RESOLVED = 'resolved';

    /** More than 10 alerts of one rule opened at once on one server, for one daemon user. */
    public const SUMMARY = 'summary';

    /** @param  array<string, mixed>  $alert */
    public function __construct(
        public readonly int $companyId,
        public readonly array $alert,
        public readonly string $change,
        public readonly bool $summarized = false,
    ) {}

    public function broadcastWhen(): bool
    {
        return $this->change === self::OPENED || $this->change === self::RESOLVED;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('monitoring.'.$this->companyId);
    }

    public function broadcastAs(): string
    {
        return 'alert';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->alert;
    }
}
