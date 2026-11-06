<?php

use App\Domain\Support\Import\SupportImporter;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Models\ReportAttachment;
use App\Domain\Support\Models\ReportMessage;
use App\Domain\Support\Models\RoadmapItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReportFixtures as Reports;
use Tests\Support\StatusPageFixtures as Fixtures;

/**
 * monitoreo's portal_reports, portal_report_messages, portal_report_attachments and report_roadmap
 * (migrations 00003..00007, 00011, 00017) as TEMPORARY tables of a second connection to the test
 * database; the importer reads them through `monitoreo_test`.
 */
function createLegacyReportTables(): void
{
    config(['database.connections.monitoreo_test' => config('database.connections.mysql')]);
    $legacy = DB::connection('monitoreo_test');
    $legacy->statement("CREATE TEMPORARY TABLE portal_reports (id BIGINT UNSIGNED PRIMARY KEY, project_id BIGINT UNSIGNED NOT NULL, client_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NULL, user_email VARCHAR(255) NOT NULL, part VARCHAR(8) NULL, urgency VARCHAR(16) NOT NULL, description TEXT NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'received', screenshot_path VARCHAR(255) NULL, screenshot_type VARCHAR(32) NULL, screenshot_size INT NULL,
        live_check LONGTEXT NOT NULL, open_issues LONGTEXT NOT NULL, recent_errors LONGTEXT NULL, browser LONGTEXT NOT NULL, ip VARCHAR(64) NOT NULL,
        created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL, resolved_at DATETIME(6) NULL, last_client_message_at DATETIME(6) NULL,
        last_team_message_at DATETIME(6) NULL, client_seen_at DATETIME(6) NULL, team_seen_at DATETIME(6) NULL, server_snapshot LONGTEXT NULL)");
    $legacy->statement('CREATE TEMPORARY TABLE portal_report_messages (id BIGINT UNSIGNED PRIMARY KEY, report_id BIGINT UNSIGNED NOT NULL, author_kind VARCHAR(8) NOT NULL,
        user_id BIGINT UNSIGNED NULL, author VARCHAR(255) NOT NULL, body TEXT NOT NULL, created_at DATETIME(6) NOT NULL, internal TINYINT(1) NOT NULL DEFAULT 0)');
    $legacy->statement('CREATE TEMPORARY TABLE portal_report_attachments (id BIGINT UNSIGNED PRIMARY KEY, message_id BIGINT UNSIGNED NOT NULL, path VARCHAR(255) NOT NULL,
        content_type VARCHAR(32) NOT NULL, size INT NOT NULL, created_at DATETIME(6) NOT NULL)');
    $legacy->statement('CREATE TEMPORARY TABLE report_roadmap (user_id BIGINT UNSIGNED NOT NULL, report_id BIGINT UNSIGNED NOT NULL, position INT NOT NULL,
        created_at DATETIME(6) NOT NULL, PRIMARY KEY (user_id, report_id))');

    $at = '2026-10-01 12:00:00.123456';
    $legacy->table('portal_reports')->insert(['id' => 77, 'project_id' => 10, 'client_id' => 1, 'user_id' => 3, 'user_email' => 'dueno@playinn.com', 'part' => 'app',
        'urgency' => 'blocking', 'description' => 'No puedo cobrar.', 'status' => 'in_review', 'screenshot_path' => 'reports/2026/10/aaaa.png',
        'screenshot_type' => 'image/png', 'screenshot_size' => null, 'live_check' => '{"status":"down","message":"Detectamos un problema en la App: no responde.","parts":[]}',
        'open_issues' => '[]', 'recent_errors' => null, 'browser' => '{"screen":"412x915"}', 'ip' => '190.1.2.3', 'created_at' => $at, 'updated_at' => $at,
        'last_client_message_at' => $at, 'team_seen_at' => '2026-10-01 12:00:00.000001', 'server_snapshot' => '{"taken_at":"2026-10-01T12:00:00Z","servers":[]}']);
    $legacy->table('portal_report_messages')->insert([
        ['id' => 500, 'report_id' => 77, 'author_kind' => 'client', 'user_id' => 3, 'author' => 'dueno@playinn.com', 'body' => 'Sigue pasando.', 'created_at' => $at, 'internal' => 0],
        ['id' => 501, 'report_id' => 77, 'author_kind' => 'team', 'user_id' => 1, 'author' => 'teamviolet@nexosmart.com.ar', 'body' => 'Es el gateway.', 'created_at' => $at, 'internal' => 1],
    ]);
    $legacy->table('portal_report_attachments')->insert(['id' => 900, 'message_id' => 500, 'path' => 'reports/2026/10/bbbb.png', 'content_type' => 'image/png', 'size' => 3, 'created_at' => $at]);
    $legacy->table('report_roadmap')->insert([
        ['user_id' => 1, 'report_id' => 77, 'position' => 1, 'created_at' => $at],
        ['user_id' => 9, 'report_id' => 77, 'position' => 1, 'created_at' => $at], // nobody on the team any more
    ]);
}

afterEach(fn () => DB::purge('monitoreo_test'));

it('imports the reports with their chat, images and roadmaps, keeping their ids (CA-13.23)', function () {
    Storage::fake('local');
    $admin = provisionCompany('teamviolet@nexosmart.com.ar');
    $project = Fixtures::project($admin);
    $owner = Reports::clientUser($project, 'dueno@playinn.com');
    // What StatusPageImporter left in import_mappings.
    foreach ([['clients', 1, 'client', $project->client_id], ['portal_projects', 10, 'project', $project->id], ['users', 1, 'user', $admin->id],
        ['users', 3, 'user', $owner->id]] as [$table, $legacyId, $type, $targetId]) {
        DB::table('import_mappings')->insert(['source' => 'monitoreo', 'source_table' => $table, 'source_id' => $legacyId, 'target_type' => $type, 'target_id' => $targetId]);
    }
    createLegacyReportTables();
    $storageDir = storage_path('framework/testing/monitoreo-'.uniqid());
    File::ensureDirectoryExists("{$storageDir}/reports/2026/10");
    File::put("{$storageDir}/reports/2026/10/aaaa.png", Reports::png()); // the chat image (bbbb.png) is gone
    $importer = new SupportImporter('monitoreo_test');

    try {
        $counts = ['portal_reports' => 1, 'portal_report_messages' => 2, 'portal_report_attachments' => 1, 'report_roadmap' => 1];
        expect($importer->import($admin->company_id, $storageDir))->toBe($counts)
            ->and($importer->missingFiles)->toBe(['reports/2026/10/bbbb.png']);

        $report = Report::query()->sole();
        expect($report->only(['id', 'project_id', 'client_id', 'user_id', 'status', 'screenshot_size', 'ip']))->toBe([
            'id' => 77, 'project_id' => $project->id, 'client_id' => $project->client_id, 'user_id' => $owner->id, 'status' => 'in_review',
            'screenshot_size' => 0, 'ip' => '190.1.2.3',
        ])
            ->and($report->created_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-01 12:00:00.123456')
            ->and($report->unreadByTeam())->toBeTrue() // the microseconds survive the copy
            ->and($report->live_check->message)->toBe('Detectamos un problema en la App: no responde.')
            ->and(ReportMessage::query()->orderBy('id')->get(['id', 'user_id', 'internal'])->toArray())->toBe([
                ['id' => 500, 'user_id' => $owner->id, 'internal' => false], ['id' => 501, 'user_id' => $admin->id, 'internal' => true],
            ])
            ->and(ReportAttachment::query()->sole()->only(['id', 'message_id']))->toBe(['id' => 900, 'message_id' => 500])
            ->and(RoadmapItem::query()->get(['user_id', 'report_id'])->toArray())->toBe([['user_id' => $admin->id, 'report_id' => 77]]);
        Storage::disk('local')->assertExists('reports/2026/10/aaaa.png');

        // The old links keep working: the team opens #77 and its screenshot.
        Reports::signIn($admin);
        $this->getJson('/api/v1/monitoring/portal-admin/reports/77')->assertOk()->assertJsonPath('data.messages.1.internal', true)
            ->assertJsonPath('data.server_snapshot.servers', []);
        $this->get('/api/v1/monitoring/portal-admin/reports/77/screenshot')->assertOk()->assertHeader('Content-Type', 'image/png');

        // Running it again gives the same result; the legacy tables are only read.
        expect($importer->import($admin->company_id, $storageDir))->toBe($counts)
            ->and(Report::query()->count())->toBe(1)->and(ReportMessage::query()->count())->toBe(2)
            ->and(DB::connection('monitoreo_test')->table('portal_reports')->value('status'))->toBe('in_review');

        $importer->wipe();
        expect(Report::query()->count())->toBe(0)->and(DB::connection('monitoreo_test')->table('portal_reports')->count())->toBe(1);
    } finally {
        File::deleteDirectory($storageDir);
    }
});
