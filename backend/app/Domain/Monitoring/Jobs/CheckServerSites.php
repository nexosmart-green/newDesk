<?php

namespace App\Domain\Monitoring\Jobs;

use App\Domain\Monitoring\Actions\ApplyAlertResults;
use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Events\SnapshotSaved;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use App\Domain\Monitoring\Models\SiteCheck;
use App\Domain\Monitoring\Sites\SiteChecker;
use App\Domain\Monitoring\Sites\SiteTargets;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One round of site checks of one server (RF-13.05, monitoreo Poller.checkSites), every 5 min
 * from the poller. It runs on the `monitoring` queue so a dead site (up to ~65 s with retries)
 * never holds the polling loop; one round per server at a time.
 */
final class CheckServerSites implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** The round's own deadline (D-106); names it cuts keep their last result. */
    public const DEADLINE = 120;

    public const MAX_SITES = 50;

    /** The next round comes in 5 min: a failed one is not retried. */
    public int $tries = 1;

    public int $timeout = self::DEADLINE + 30;

    /** Frees the server if a worker died mid-round. */
    public int $uniqueFor = 600;

    public function __construct(public readonly int $serverId)
    {
        $this->onQueue('monitoring');
    }

    public function uniqueId(): string
    {
        return (string) $this->serverId;
    }

    public function handle(SiteChecker $checker, ApplyAlertResults $applyAlerts): void
    {
        $server = Server::query()->find($this->serverId);
        $sites = ServerSnapshot::query()->where('server_id', $this->serverId)->where('section', 'sites')->value('data');
        if ($server === null || $sites === null || $sites === '') {
            return;
        }
        $names = SiteTargets::fromSites(json_decode($sites));
        if (count($names) > self::MAX_SITES) {
            Log::warning('monitoring sites: too many sites, checking the first ones', ['server' => $server->name, 'sites' => count($names)]);
            $names = array_slice($names, 0, self::MAX_SITES);
        }

        $checks = $checker->checkAll($names, CarbonImmutable::now()->addSeconds(self::DEADLINE));
        $this->save($server, $checks);
        SiteCheck::query()->where('server_id', $server->id)->whereNotIn('server_name', $names)->delete();

        $now = CarbonImmutable::now()->utc();
        try {
            event(new SnapshotSaved($server->company_id, $server->id, 'site_checks', $now->toIso8601ZuluString()));
        } catch (Throwable $e) {
            Log::warning('monitoring sites: broadcast failed', ['error' => $e->getMessage()]);
        }
        $current = SiteCheck::query()->where('server_id', $server->id)->orderBy('server_name')->get()
            ->map(fn (SiteCheck $check) => $check->toPayload($now));
        $applyAlerts($server, AlertRules::sites($current), $now);
    }

    /** @param  array<string, array<string, mixed>>  $checks */
    private function save(Server $server, array $checks): void
    {
        if ($checks === []) {
            return;
        }
        $rows = [];
        $history = [];
        foreach ($checks as $name => $check) {
            $row = ['server_id' => $server->id, 'server_name' => $name, 'company_id' => $server->company_id, ...$check];
            $rows[] = $row;
            $history[] = array_intersect_key($row, array_flip(['company_id', 'server_id', 'server_name', 'status_code', 'latency_ms', 'ok', 'checked_at']));
        }

        DB::transaction(function () use ($rows, $history) {
            SiteCheck::query()->upsert($rows, ['server_id', 'server_name'], [
                'url', 'status_code', 'latency_ms', 'ok', 'error', 'checked_at',
                // A failed TLS handshake has no certificate: the last known expiry stays.
                'ssl_expires_at' => DB::raw('COALESCE(VALUES(ssl_expires_at), ssl_expires_at)'),
            ]);
            DB::table('monitoring_site_check_history')->insert($history);
        });
    }
}
