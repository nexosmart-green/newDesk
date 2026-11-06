<?php

namespace App\Domain\StatusPage\Status;

use Carbon\CarbonImmutable;

/** One GET of a component's URL (monitoreo sitecheck.ProbeResult). Without an answer, only `error`. */
final class ProbeResult
{
    public function __construct(
        public readonly ?int $statusCode = null,
        public readonly ?int $latencyMs = null,
        public readonly ?CarbonImmutable $sslExpiresAt = null,
        public readonly string $body = '',
        public readonly string $error = '',
    ) {}
}
