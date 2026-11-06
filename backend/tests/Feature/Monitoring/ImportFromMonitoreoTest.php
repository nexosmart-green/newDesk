<?php

use App\Domain\Monitoring\Import\LegacySecretBox;
use App\Domain\Monitoring\Import\MonitoreoImporter;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\GoSealedTokens;

/**
 * monitoreo's tables (00001_init.sql shapes) as TEMPORARY tables of the test connection: they
 * live inside the test transaction, and the importer reads them through that connection.
 */
function createLegacyMonitoreoTables(): void
{
    foreach (['servers', 'server_snapshots', 'metrics_raw', 'metrics_hourly'] as $table) {
        DB::statement("DROP TEMPORARY TABLE IF EXISTS {$table}");
    }
    $metrics = 'cpu DOUBLE NOT NULL, memory DOUBLE NOT NULL, swap DOUBLE NOT NULL, disk DOUBLE NOT NULL, load1 DOUBLE NOT NULL, rx_bps DOUBLE NOT NULL, tx_bps DOUBLE NOT NULL';
    DB::statement('CREATE TEMPORARY TABLE servers (id BIGINT UNSIGNED PRIMARY KEY, name VARCHAR(100) NOT NULL, ip VARCHAR(64) NOT NULL,
        ssh_port INT NULL, agent_port INT NOT NULL, token_enc VARBINARY(512) NOT NULL, tls_fingerprint CHAR(64) NOT NULL,
        enabled TINYINT(1) NOT NULL, status VARCHAR(16) NOT NULL, last_seen_at DATETIME(6) NULL, hostname VARCHAR(255) NULL,
        os VARCHAR(255) NULL, agent_version VARCHAR(64) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL)');
    DB::statement('CREATE TEMPORARY TABLE server_snapshots (server_id BIGINT UNSIGNED NOT NULL, section VARCHAR(32) NOT NULL,
        collected_at DATETIME(6) NULL, fetched_at DATETIME(6) NOT NULL, agent_version VARCHAR(64) NULL, data LONGTEXT NULL,
        warnings LONGTEXT NULL, error TEXT NULL, PRIMARY KEY (server_id, section))');
    DB::statement("CREATE TEMPORARY TABLE metrics_raw (id BIGINT UNSIGNED PRIMARY KEY, server_id BIGINT UNSIGNED NOT NULL, ts DATETIME(6) NOT NULL, {$metrics})");
    DB::statement("CREATE TEMPORARY TABLE metrics_hourly (server_id BIGINT UNSIGNED NOT NULL, hour DATETIME NOT NULL, {$metrics},
        cpu_max DOUBLE NOT NULL, memory_max DOUBLE NOT NULL, swap_max DOUBLE NOT NULL, disk_max DOUBLE NOT NULL, load1_max DOUBLE NOT NULL,
        samples INT NOT NULL, PRIMARY KEY (server_id, hour))");

    $server = ['ssh_port' => null, 'agent_port' => 9100, 'enabled' => 1, 'status' => 'online', 'last_seen_at' => '2026-11-06 11:59:30.123456',
        'hostname' => 'mail', 'os' => 'Ubuntu 24.04', 'agent_version' => 'v0.9', 'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-02-01 10:00:00'];
    DB::table('servers')->insert([
        ['id' => 7, 'name' => 'reaquila', 'ip' => '203.0.113.7', 'token_enc' => hex2bin(GoSealedTokens::FOR_SERVER_7), 'tls_fingerprint' => str_repeat('a', 64), ...$server],
        ['id' => 9, 'name' => 'viejo', 'ip' => '203.0.113.9', 'token_enc' => hex2bin(GoSealedTokens::WITHOUT_ROW), 'tls_fingerprint' => str_repeat('b', 64), ...$server],
    ]);
    DB::table('server_snapshots')->insert(['server_id' => 7, 'section' => 'metrics', 'collected_at' => '2026-11-06 12:00:00',
        'fetched_at' => '2026-11-06 12:00:01', 'agent_version' => 'v0.9', 'data' => '{"cpu":{"usage_percent":3.1},"extra":{}}', 'warnings' => '["x"]']);
    $point = ['cpu' => 3.1, 'memory' => 46, 'swap' => 2, 'disk' => 69, 'load1' => 0.1, 'rx_bps' => 10, 'tx_bps' => 5];
    DB::table('metrics_raw')->insert(['id' => 500, 'server_id' => 7, 'ts' => '2026-11-06 12:00:00', ...$point]);
    DB::table('metrics_hourly')->insert(['server_id' => 7, 'hour' => '2026-11-06 11:00:00', ...$point, 'cpu_max' => 9,
        'memory_max' => 50, 'swap_max' => 3, 'disk_max' => 69, 'load1_max' => 0.4, 'samples' => 60]);
}

it('copies monitoreo keeping ids and re-sealing each token for newDesk (CA-13.23, CA-13.24)', function () {
    $admin = provisionCompany();
    createLegacyMonitoreoTables();
    $importer = new MonitoreoImporter(new LegacySecretBox(GoSealedTokens::MASTER_KEY), DB::getDefaultConnection());

    $counts = ['servers' => 2, 'server_snapshots' => 1, 'metrics_raw' => 1, 'metrics_hourly' => 1];
    expect($importer->import($admin->company_id))->toBe($counts);

    $server = Server::query()->findOrFail(7);
    expect($server->company_id)->toBe($admin->company_id)
        ->and($server->token)->toBe(GoSealedTokens::FOR_SERVER_7_PLAIN)
        ->and(Server::query()->findOrFail(9)->token)->toBe(GoSealedTokens::WITHOUT_ROW_PLAIN)
        ->and($server->status)->toBe('online')
        ->and($server->created_at->toDateTimeString())->toBe('2026-01-01 10:00:00')
        ->and(ServerSnapshot::query()->sole()->data)->toBe('{"cpu":{"usage_percent":3.1},"extra":{}}')
        ->and(DB::table('monitoring_metrics_raw')->sole()->id)->toBe(500)
        ->and((float) DB::table('monitoring_metrics_hourly')->sole()->cpu_max)->toBe(9.0);

    // Running it again gives the same result.
    expect($importer->import($admin->company_id))->toBe($counts)
        ->and(Server::query()->count())->toBe(2)
        ->and(DB::table('monitoring_metrics_raw')->count())->toBe(1);

    // --fresh empties only newDesk's monitoring tables.
    $importer->wipe();
    expect(Server::query()->count())->toBe(0)
        ->and(DB::table('monitoring_metrics_hourly')->count())->toBe(0)
        ->and(DB::table('servers')->count())->toBe(2);
});

it('asks which company owns the servers when there are several', function () {
    provisionCompany('a@nexo.com');
    provisionCompany('b@other.com');

    expect(Artisan::call('monitoring:import'))->toBe(1)
        ->and(Artisan::output())->toContain('--company');
});
