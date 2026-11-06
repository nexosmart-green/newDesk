<?php

namespace App\Domain\Monitoring\Agent;

/**
 * The agent's endpoints, GET /v1/<section> (monitoreo internal/contract).
 */
final class Section
{
    public const HEALTH = 'health';

    public const METRICS = 'metrics';

    public const SYSTEM = 'system';

    /** Every section except health, in the order monitoreo polls them (contract.AllSections). */
    public const ALL = [
        self::METRICS, self::SYSTEM, 'services', 'pm2', 'supervisor', 'cron', 'projects', 'sites',
        'dependencies', 'packages', 'databases', 'ports', 'security', 'large_files', 'vulnerabilities',
    ];
}
