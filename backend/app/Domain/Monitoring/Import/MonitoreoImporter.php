<?php

namespace App\Domain\Monitoring\Import;

use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Copies monitoreo's servers, snapshots and metrics (RF-13.12). Reads the legacy database and
 * never writes it (CA-13.23); ids are kept and every row is upserted by key, so running it
 * again gives the same result. Each token is decrypted and sealed again for newDesk (CA-13.24).
 */
final class MonitoreoImporter
{
    private const MONITORING_TABLES = [
        'monitoring_metrics_hourly', 'monitoring_metrics_raw', 'monitoring_server_snapshots', 'monitoring_servers',
    ];

    private const METRICS = ['cpu', 'memory', 'swap', 'disk', 'load1', 'rx_bps', 'tx_bps'];

    private const HOURLY_EXTRA = ['cpu_max', 'memory_max', 'swap_max', 'disk_max', 'load1_max', 'samples'];

    /** @param  string  $connection  the legacy database (`monitoreo_legacy`; tests pass their own) */
    public function __construct(
        private readonly LegacySecretBox $box,
        private readonly string $connection = 'monitoreo_legacy',
    ) {}

    /** --fresh: only newDesk's monitoring tables, children first. */
    public function wipe(): void
    {
        foreach (self::MONITORING_TABLES as $table) {
            DB::table($table)->delete();
        }
    }

    /** @return array<string, int> rows copied per legacy table */
    public function import(int $companyId): array
    {
        $legacy = DB::connection($this->connection);

        return [
            'servers' => $this->servers($legacy, $companyId),
            'server_snapshots' => $this->snapshots($legacy, $companyId),
            'metrics_raw' => $this->rawMetrics($legacy, $companyId),
            'metrics_hourly' => $this->hourlyMetrics($legacy, $companyId),
        ];
    }

    private function servers(ConnectionInterface $legacy, int $companyId): int
    {
        $rows = $legacy->table('servers')->orderBy('id')->get();
        foreach ($rows as $row) {
            $server = Server::query()->withoutGlobalScopes()->find($row->id) ?? new Server;
            $server->timestamps = false;
            $server->forceFill([
                'id' => $row->id,
                'company_id' => $companyId,
                'name' => $row->name,
                'ip' => $row->ip,
                'ssh_port' => $row->ssh_port,
                'agent_port' => $row->agent_port,
                'token' => $this->box->decrypt($row->token_enc, LegacySecretBox::row('servers.token_enc', $row->id)),
                'tls_fingerprint' => $row->tls_fingerprint,
                'enabled' => (bool) $row->enabled,
                'status' => $row->status,
                'last_seen_at' => $row->last_seen_at,
                'hostname' => $row->hostname,
                'os' => $row->os,
                'agent_version' => $row->agent_version,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])->save();
        }

        return $rows->count();
    }

    private function snapshots(ConnectionInterface $legacy, int $companyId): int
    {
        $count = 0;
        // Small chunks: `data` can be large.
        $legacy->table('server_snapshots')->orderBy('server_id')->orderBy('section')
            ->chunk(100, function ($rows) use ($companyId, &$count) {
                ServerSnapshot::query()->upsert(
                    $rows->map(fn (object $row) => ['company_id' => $companyId, ...(array) $row])->all(),
                    ['server_id', 'section'],
                    ['company_id', 'collected_at', 'fetched_at', 'agent_version', 'data', 'warnings', 'error'],
                );
                $count += $rows->count();
            });

        return $count;
    }

    private function rawMetrics(ConnectionInterface $legacy, int $companyId): int
    {
        $count = 0;
        $legacy->table('metrics_raw')->chunkById(2000, function ($rows) use ($companyId, &$count) {
            DB::table('monitoring_metrics_raw')->upsert(
                $rows->map(fn (object $row) => ['company_id' => $companyId, ...(array) $row])->all(),
                ['id'],
                ['company_id', 'server_id', 'ts', ...self::METRICS],
            );
            $count += $rows->count();
        });

        return $count;
    }

    private function hourlyMetrics(ConnectionInterface $legacy, int $companyId): int
    {
        $count = 0;
        $legacy->table('metrics_hourly')->orderBy('server_id')->orderBy('hour')
            ->chunk(2000, function ($rows) use ($companyId, &$count) {
                DB::table('monitoring_metrics_hourly')->upsert(
                    $rows->map(fn (object $row) => ['company_id' => $companyId, ...(array) $row])->all(),
                    ['server_id', 'hour'],
                    ['company_id', ...self::METRICS, ...self::HOURLY_EXTRA],
                );
                $count += $rows->count();
            });

        return $count;
    }
}
