<?php

namespace App\Domain\Monitoring\Events;

/**
 * An alert opened, turned critical, resolved, or a flood of openings summarized (monitoreo
 * Engine.OnChange). `alert` is monitoreo's store.Alert JSON; a summary's has no id.
 *
 * A domain event: the Discord notices (RF-13.09) listen here and post `summary` instead of each
 * `summarized` opening (D-107, CA-13.11). The browsers hear AlertBroadcast, sent apart and best
 * effort, so Reverb being down never stops a notice.
 */
final class AlertChanged
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
}
