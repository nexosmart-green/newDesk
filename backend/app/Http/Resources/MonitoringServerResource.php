<?php

namespace App\Http\Resources;

use App\Domain\Monitoring\Agent\MetricsSummary;
use App\Domain\Monitoring\Models\Server;
use Illuminate\Http\Request;

/**
 * monitoreo's ServerSummary, field for field (CA-13.01: no token, no pin). Needs `metricsSnapshot` loaded.
 *
 * @mixin Server
 */
class MonitoringServerResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ip' => $this->ip,
            'ssh_port' => $this->ssh_port,
            'agent_port' => $this->agent_port,
            'enabled' => $this->enabled,
            'status' => $this->displayStatus(),
            'last_seen_at' => $this->last_seen_at?->toIso8601ZuluString(),
            'hostname' => $this->hostname,
            'os' => $this->os,
            'metrics' => MetricsSummary::fromSnapshot($this->metricsSnapshot),
            'active_alerts' => 0, // alerts arrive in the next phase
            'agent_version' => $this->agent_version,
        ];
    }
}
