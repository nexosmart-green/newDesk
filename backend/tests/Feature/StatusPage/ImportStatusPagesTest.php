<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Import\LegacySecretBox;
use App\Domain\Projects\Models\Project;
use App\Domain\StatusPage\Import\StatusPageImporter;
use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Models\IncidentNote;
use App\Domain\StatusPage\Models\Period;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GoSealedTokens;
use Tests\Support\StatusPageFixtures as Fixtures;

/** Seals a value the way monitoreo's secret.Box does (checked against Go's own output in LegacySecretBoxTest). */
function sealLikeMonitoreo(string $plain, string $row): string
{
    $key = hash_hmac('sha256', 'monitoreo:encrypt', base64_decode(GoSealedTokens::MASTER_KEY), true);
    $nonce = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $row);

    return $nonce.$cipher.$tag;
}

/**
 * monitoreo's clients, users and portal tables (migrations 00002..00017) as TEMPORARY tables of a
 * second connection to the test database: they shadow newDesk's `clients` and `users` only there,
 * so the importer reads them through `monitoreo_test` and writes newDesk's through the default one.
 */
function createLegacyStatusPageTables(): void
{
    config(['database.connections.monitoreo_test' => config('database.connections.mysql')]);
    $legacy = DB::connection('monitoreo_test');
    $legacy->statement('CREATE TEMPORARY TABLE clients (id BIGINT UNSIGNED PRIMARY KEY, name VARCHAR(120) NOT NULL, active TINYINT(1) NOT NULL,
        created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL)');
    $legacy->statement('CREATE TEMPORARY TABLE users (id BIGINT UNSIGNED PRIMARY KEY, email VARCHAR(255) NOT NULL, is_admin TINYINT(1) NOT NULL,
        client_id BIGINT UNSIGNED NULL, discord_user_id VARCHAR(20) NULL, active TINYINT(1) NOT NULL, created_at DATETIME(6) NOT NULL, last_login_at DATETIME(6) NULL)');
    $legacy->statement('CREATE TEMPORARY TABLE portal_projects (id BIGINT UNSIGNED PRIMARY KEY, client_id BIGINT UNSIGNED NOT NULL, slug VARCHAR(60) NOT NULL,
        name VARCHAR(120) NOT NULL, primary_color CHAR(7) NULL, logo MEDIUMBLOB NULL, logo_type VARCHAR(32) NULL, logo_updated_at DATETIME(6) NULL,
        domain VARCHAR(253) NULL, domain_expires_at DATETIME(6) NULL, domain_expires_manual DATE NULL, domain_checked_at DATETIME(6) NULL,
        domain_notice_level TINYINT NOT NULL, errors_url VARCHAR(512) NULL, errors_token_enc VARBINARY(512) NULL, discord_channel_id VARCHAR(20) NULL,
        active TINYINT(1) NOT NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL)');
    $legacy->statement("CREATE TEMPORARY TABLE portal_project_developers (project_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
        role ENUM('lead', 'secondary') NOT NULL, PRIMARY KEY (project_id, user_id))");
    $legacy->statement("CREATE TEMPORARY TABLE portal_components (id BIGINT UNSIGNED PRIMARY KEY, project_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(16) NOT NULL,
        label VARCHAR(120) NOT NULL, parts VARCHAR(32) NOT NULL DEFAULT '', position INT NOT NULL DEFAULT 0, url VARCHAR(512) NULL, server_id BIGINT UNSIGNED NULL,
        target VARCHAR(255) NULL, target_owner VARCHAR(64) NULL, max_age_hours INT NULL, slow_ms INT NULL, status VARCHAR(16) NOT NULL, status_since DATETIME(6) NULL,
        phrase VARCHAR(120) NOT NULL DEFAULT '', detail VARCHAR(255) NOT NULL DEFAULT '', bad_streak INT NOT NULL DEFAULT 0, bad_since DATETIME(6) NULL,
        down_since DATETIME(6) NULL, last_checked_at DATETIME(6) NULL, last_ok_at DATETIME(6) NULL, latency_ms INT NULL, status_code INT NULL,
        ssl_expires_at DATETIME(6) NULL, created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL)");
    $legacy->statement('CREATE TEMPORARY TABLE portal_checks (id BIGINT UNSIGNED PRIMARY KEY, component_id BIGINT UNSIGNED NOT NULL, checked_at DATETIME(6) NOT NULL,
        ok TINYINT(1) NOT NULL, status_code INT NULL, latency_ms INT NULL)');
    $legacy->statement('CREATE TEMPORARY TABLE portal_checks_hourly (component_id BIGINT UNSIGNED NOT NULL, hour DATETIME NOT NULL, samples INT NOT NULL,
        failures INT NOT NULL, avg_ms DOUBLE NULL, max_ms INT NULL, PRIMARY KEY (component_id, hour))');
    $legacy->statement("CREATE TEMPORARY TABLE portal_periods (id BIGINT UNSIGNED PRIMARY KEY, component_id BIGINT UNSIGNED NOT NULL, status VARCHAR(16) NOT NULL,
        started_at DATETIME(6) NOT NULL, ended_at DATETIME(6) NULL, detail VARCHAR(255) NOT NULL DEFAULT '')");
    $legacy->statement('CREATE TEMPORARY TABLE portal_incident_notes (id BIGINT UNSIGNED PRIMARY KEY, period_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL,
        author VARCHAR(255) NOT NULL, body TEXT NOT NULL, created_at DATETIME(6) NOT NULL)');
    $legacy->statement('CREATE TEMPORARY TABLE portal_project_folders (id BIGINT UNSIGNED PRIMARY KEY, project_id BIGINT UNSIGNED NOT NULL, server_id BIGINT UNSIGNED NOT NULL,
        path VARCHAR(512) NOT NULL, created_at DATETIME(6) NOT NULL)');
    $legacy->statement('CREATE TEMPORARY TABLE portal_pagespeed (id BIGINT UNSIGNED PRIMARY KEY, component_id BIGINT UNSIGNED NOT NULL, strategy VARCHAR(8) NOT NULL,
        score INT NULL, lcp_ms INT NULL, cls DOUBLE NULL, inp_ms INT NULL, fcp_ms INT NULL, tbt_ms INT NULL, field TINYINT(1) NOT NULL, error VARCHAR(255) NULL,
        measured_at DATETIME(6) NOT NULL)');

    $at = '2026-10-01 12:00:00.123456';
    $legacy->table('clients')->insert(['id' => 1, 'name' => 'Playinn', 'active' => 1, 'created_at' => $at, 'updated_at' => $at]);
    $legacy->table('users')->insert([
        ['id' => 1, 'email' => 'TeamViolet@nexosmart.com.ar', 'is_admin' => 1, 'client_id' => null, 'discord_user_id' => '1403351571303104612', 'active' => 1, 'created_at' => $at, 'last_login_at' => null],
        ['id' => 2, 'email' => 'dev@nexosmart.com.ar', 'is_admin' => 0, 'client_id' => null, 'discord_user_id' => null, 'active' => 1, 'created_at' => $at, 'last_login_at' => null],
        ['id' => 3, 'email' => 'Dueno@Playinn.com', 'is_admin' => 0, 'client_id' => 1, 'discord_user_id' => null, 'active' => 1, 'created_at' => $at, 'last_login_at' => $at],
    ]);
    $legacy->table('portal_projects')->insert(['id' => 10, 'client_id' => 1, 'slug' => 'playinn-cafe', 'name' => 'Playinn Café', 'primary_color' => '#e11d48',
        'logo' => 'png-bytes', 'logo_type' => 'image/png', 'logo_updated_at' => $at, 'domain' => 'playinn.com', 'domain_expires_at' => '2027-03-01 00:00:00',
        'domain_expires_manual' => null, 'domain_checked_at' => $at, 'domain_notice_level' => 1, 'errors_url' => 'https://api.playinn.com/api/errors',
        'errors_token_enc' => sealLikeMonitoreo('token-del-proyecto', LegacySecretBox::row('portal_projects.errors_token_enc', 10)),
        'discord_channel_id' => '1427806155307745290', 'active' => 1, 'created_at' => $at, 'updated_at' => $at]);
    $legacy->table('portal_project_developers')->insert([['project_id' => 10, 'user_id' => 1, 'role' => 'lead'], ['project_id' => 10, 'user_id' => 2, 'role' => 'secondary']]);
    $legacy->table('portal_components')->insert(['id' => 55, 'project_id' => 10, 'kind' => 'http', 'label' => 'Sitio web', 'parts' => 'web', 'position' => 0,
        'url' => 'https://playinn.com/', 'server_id' => 7, 'status' => 'operational', 'status_since' => $at, 'phrase' => 'Funciona normalmente',
        'detail' => 'HTTP 200 en 90 ms', 'created_at' => $at, 'updated_at' => $at]);
    $legacy->table('portal_checks')->insert(['id' => 900, 'component_id' => 55, 'checked_at' => $at, 'ok' => 1, 'status_code' => 200, 'latency_ms' => 90]);
    $legacy->table('portal_checks_hourly')->insert(['component_id' => 55, 'hour' => '2026-10-01 11:00:00', 'samples' => 60, 'failures' => 1, 'avg_ms' => 95.5, 'max_ms' => 300]);
    $legacy->table('portal_periods')->insert(['id' => 300, 'component_id' => 55, 'status' => 'down', 'started_at' => '2026-09-30 10:00:00', 'ended_at' => '2026-09-30 10:20:00']);
    $legacy->table('portal_incident_notes')->insert(['id' => 400, 'period_id' => 300, 'user_id' => 1, 'author' => 'teamviolet@nexosmart.com.ar', 'body' => 'Resuelto.', 'created_at' => $at]);
    $legacy->table('portal_project_folders')->insert(['id' => 500, 'project_id' => 10, 'server_id' => 7, 'path' => '/var/www/playinn', 'created_at' => $at]);
    $legacy->table('portal_pagespeed')->insert(['id' => 600, 'component_id' => 55, 'strategy' => 'mobile', 'score' => 71, 'field' => 1, 'measured_at' => $at]);
}

afterEach(fn () => DB::purge('monitoreo_test'));

it('imports clients, users and status pages, mapping legacy rows and keeping component ids (CA-13.23)', function () {
    Storage::fake('local');
    $admin = provisionCompany('teamviolet@nexosmart.com.ar');
    Fixtures::server($admin, 'reaquila', ['id' => 7]);
    createLegacyStatusPageTables();
    $importer = new StatusPageImporter(new LegacySecretBox(GoSealedTokens::MASTER_KEY), 'monitoreo_test');

    $counts = ['team_users' => 2, 'clients' => 1, 'client_users' => 1, 'portal_projects' => 1, 'portal_components' => 1, 'portal_checks' => 1,
        'portal_checks_hourly' => 1, 'portal_periods' => 1, 'portal_incident_notes' => 1, 'portal_project_folders' => 1, 'portal_pagespeed' => 1];
    expect($importer->import($admin->company_id))->toBe($counts)
        ->and($importer->invited)->toBe(['dev@nexosmart.com.ar']);

    $dev = User::query()->where('email', 'dev@nexosmart.com.ar')->sole();
    expect($dev->status)->toBe(UserStatus::Invited)->and($dev->getAuthPassword())->toBeNull()
        ->and($dev->role->name)->toBe(DefaultRoles::DEVELOPER)
        ->and($admin->fresh()->discord_user_id)->toBe('1403351571303104612');
    $owner = User::query()->where('client_id', '!=', null)->sole();
    $client = Client::query()->sole();
    expect($owner->email)->toBe('dueno@playinn.com') // lowercased: the portal login matches it byte for byte (D-076)
        ->and($owner->client_id)->toBe($client->id)
        ->and($owner->role->name)->toBe(DefaultRoles::CLIENT);

    $project = Project::query()->sole();
    expect($project->only(['slug', 'name', 'code_prefix', 'client_id', 'portal_active', 'discord_channel_id', 'domain_notice_level']))->toBe([
        'slug' => 'playinn-cafe', 'name' => 'Playinn Café', 'code_prefix' => 'playinncafe', 'client_id' => $client->id, 'portal_active' => true,
        'discord_channel_id' => '1427806155307745290', 'domain_notice_level' => 1,
    ])
        ->and($project->errors_token)->toBe('token-del-proyecto')
        ->and($project->leader_user_id)->toBe($admin->id)
        ->and($project->members()->pluck('users.id')->sort()->values()->all())->toBe(collect([$admin->id, $dev->id])->sort()->values()->all())
        ->and($project->logo_type)->toBe('image/png')
        ->and(Storage::disk('local')->get($project->logo_path))->toBe('png-bytes');

    $component = Component::query()->sole();
    expect($component->id)->toBe(55)->and($component->project_id)->toBe($project->id)->and($component->parts)->toBe(['web'])
        ->and(Period::query()->sole()->id)->toBe(300)
        ->and(IncidentNote::query()->sole()->user_id)->toBe($admin->id)
        ->and(DB::table('status_project_folders')->sole()->project_id)->toBe($project->id)
        ->and((float) DB::table('status_checks_hourly')->sole()->avg_ms)->toBe(95.5)
        ->and(DB::table('status_pagespeed_runs')->sole()->id)->toBe(600)
        ->and(DB::table('status_checks')->sole()->id)->toBe(900);

    // Running it again gives the same result: the mapped rows are updated, nothing is duplicated.
    expect($importer->import($admin->company_id))->toBe($counts)
        ->and($importer->invited)->toBe([])
        ->and(Project::query()->count())->toBe(1)
        ->and(User::query()->count())->toBe(3)
        ->and(DB::table('import_mappings')->count())->toBe(5);

    // --fresh empties only the status page history.
    $importer->wipe();
    expect(Component::query()->count())->toBe(0)->and(Project::query()->count())->toBe(1)
        ->and(DB::connection('monitoreo_test')->table('portal_components')->count())->toBe(1);
});
