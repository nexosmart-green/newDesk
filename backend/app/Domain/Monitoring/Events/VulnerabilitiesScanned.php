<?php

namespace App\Domain\Monitoring\Events;

/**
 * A server's `vulnerabilities` section came back, and a scan was already saved (monitoreo
 * Poller.OnVulnerabilities, D-054, D-073): the saved one, read before it was replaced, and the new
 * one. Not dispatched the first time a server answers the section.
 */
final class VulnerabilitiesScanned
{
    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $next
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $serverId,
        public readonly string $serverName,
        public readonly array $previous,
        public readonly array $next,
    ) {}
}
