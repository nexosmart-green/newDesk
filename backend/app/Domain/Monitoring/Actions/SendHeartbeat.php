<?php

namespace App\Domain\Monitoring\Actions;

use App\Domain\Monitoring\Polling\PollerSignals;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every minute, pings HEALTHCHECKS_URL while the database answers and the poller ticks (monitoreo
 * heartbeatLoop, D-009): an outage of this very server still produces an alert. Silent while
 * notices are off (CA-13.20): monitoreo's heartbeat is the one that counts then.
 */
final class SendHeartbeat
{
    public function __construct(private readonly PollerSignals $signals) {}

    public function __invoke(): void
    {
        $url = config('newdesk.monitoring.healthchecks_url');
        if (! config('newdesk.monitoring.notify') || blank($url)) {
            return;
        }
        try {
            DB::select('select 1');
            $healthy = $this->signals->pollerAlive(now()->getTimestamp());
        } catch (Throwable) {
            $healthy = false;
        }
        if (! $healthy) {
            Log::warning('heartbeat skipped: panel unhealthy');

            return;
        }
        try {
            Http::timeout(10)->get($url);
        } catch (ConnectionException $e) {
            Log::warning('heartbeat failed', ['error' => $e->getMessage()]);
        }
    }
}
