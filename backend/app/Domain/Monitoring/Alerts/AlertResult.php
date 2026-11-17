<?php

namespace App\Domain\Monitoring\Alerts;

/**
 * Every observation of one rule on one server (monitoreo alerts.Result). An alert opens when its
 * target fired `required` evaluations in a row for at least `for` seconds; open alerts of the
 * rule whose target is missing here resolve.
 */
final readonly class AlertResult
{
    /** @param  list<AlertObservation>  $observations */
    public function __construct(
        public string $rule,
        public int $for,
        public int $required,
        public array $observations,
    ) {}
}
