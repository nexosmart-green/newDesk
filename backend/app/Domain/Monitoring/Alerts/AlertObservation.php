<?php

namespace App\Domain\Monitoring\Alerts;

/**
 * What one rule sees on one target in one evaluation (monitoreo alerts.Observation).
 * `target` is '' for server-wide rules (CPU, server_down…).
 */
final readonly class AlertObservation
{
    public function __construct(
        public string $target,
        public bool $firing,
        public string $severity,
        public string $message,
        public string $value,
    ) {}
}
