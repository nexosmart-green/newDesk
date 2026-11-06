<?php

use App\Domain\Monitoring\Contracts\AgentGateway;
use App\Domain\Monitoring\Models\Alert;
use App\Domain\StatusPage\Models\ProjectFolder;
use App\Domain\Support\Actions\PurgeReports;
use App\Domain\Support\Events\ReportSubmitted;
use App\Domain\Support\Models\Report;
use App\Domain\Support\Notifications\ReportSubmittedMail;
use App\Domain\Support\UploadQuota;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeAgentGateway;
use Tests\Support\ReportFixtures as Reports;
use Tests\Support\StatusPageFixtures as Fixtures;

/**
 * Port of monitoreo's reports_test.go, report_budget_test.go, upload_quota_test.go and
 * reports_recover_test.go: "Reportar un problema" (RF-13.08, D-041, D-050, D-090, D-091).
 */
beforeEach(function () {
    Storage::fake('local');
    Mail::fake();
    config(['newdesk.monitoring.notify' => true, 'newdesk.monitoring.alert_email' => 'team@nexosmart.com.ar', 'newdesk.frontend_url' => 'https://desk.test']);
    $this->admin = provisionCompany('admin@nexosmart.com.ar');
    $this->project = Fixtures::project($this->admin);
    $this->owner = Reports::clientUser($this->project, 'dueno@playinn.com');
});

// httpapi/reports_test.go TestReportAProblem
it('reports a problem with a live check, the project errors and the browser, and mails the team (RF-13.08, D-041)', function () {
    Http::fake([
        'https://playinn.test/*' => Http::response('ok'),
        'https://pagos.playinn.test/*' => Http::response('', 502),
        // The project's own bug-report endpoint (docs/error-endpoint-spec.md).
        'https://api.playinn.test/api/errors' => fn (Request $request) => $request->header('Authorization') !== ['Bearer token-del-proyecto']
            ? Http::response('', 401)
            : Http::response(['date' => '2026-10-07', 'total' => 2, 'errors' => [
                ['timestamp' => '2026-10-07 14:02:10', 'level' => 'ERROR', 'message' => 'SQLSTATE[HY000] Connection refused', 'action' => 'x', 'method' => 'POST',
                    'url' => 'https://api.playinn.com/api/pagos', 'user_id' => 5, 'ip' => '190.1.2.3', 'input' => ['card' => '4111'], 'exception_class' => 'PDOException', 'file' => 'a.php:1'],
                ['timestamp' => '2026-10-07 13:00:00', 'level' => 'WARNING', 'message' => 'slow query', 'action' => 'y', 'method' => 'GET',
                    'url' => 'https://api.playinn.com/', 'user_id' => null, 'ip' => '1.1.1.1', 'input' => null, 'exception_class' => null, 'file' => null],
            ]]),
    ]);
    $this->project->forceFill(['errors_url' => 'https://api.playinn.test/api/errors', 'errors_token' => 'token-del-proyecto'])->save();
    Fixtures::saved($this->project, ['label' => 'Sitio web', 'url' => 'https://playinn.test/']);
    // The payments API serves the app: the client reports "App", the team sees "Pagos".
    Fixtures::saved($this->project, ['kind' => 'health', 'label' => 'Pagos', 'url' => 'https://pagos.playinn.test/health', 'position' => 1, 'parts' => ['app']]);

    Reports::signIn($this->owner);
    $fields = ['description' => 'No puedo cobrar, el botón de pagar queda girando.', 'urgency' => 'blocking', 'part' => 'app',
        'page_url' => 'https://desk.test/portal/playinn', 'screen' => '412x915', 'timezone' => 'America/Argentina/Buenos_Aires', 'language' => 'es-AR'];

    // Validation never reaches the live check nor counts for the budget.
    Reports::post('playinn', ['description' => 'corto', 'urgency' => 'ya', 'part' => 'cms']) // Playinn has no CMS
        ->assertUnprocessable()->assertJsonValidationErrors(['description', 'urgency', 'part'])
        ->assertJsonPath('errors.description.0', 'Contanos qué pasa (entre 10 y 2000 caracteres).');
    Reports::post('playinn', $fields, '<svg onload=alert(1)>')
        ->assertUnprocessable()->assertJsonPath('errors.screenshot.0', 'La captura tiene que ser PNG, JPG o WebP.');
    Reports::post('playinn', $fields, str_repeat("\0", 7 << 20))
        ->assertStatus(413)->assertJsonPath('message', 'El reporte pasa de 6 MB. Probá con una captura más chica.');
    expect(Report::query()->count())->toBe(0);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'playinn.test/'));

    $created = Reports::post('playinn', $fields, Reports::png())->assertCreated()->assertJsonPath('message', 'Recibimos tu reporte.')
        ->assertJsonPath('data.live.status', 'down')
        ->assertJsonPath('data.live.components', [['label' => 'Web', 'status' => 'operational', 'phrase' => 'Funciona normalmente'],
            ['label' => 'App', 'status' => 'down', 'phrase' => 'No responde']])
        ->assertJsonPath('data.report.status', 'received')->assertJsonPath('data.report.component_label', 'App')
        ->assertJsonPath('data.report.unread', false);
    expect($created->json('data.live.message'))->toStartWith('Detectamos un problema en la App: no responde.')
        ->and($created->getContent())->not->toContain('pagos.playinn.test')->not->toContain('HTTP 502')->not->toContain('Pagos');
    $reportId = $created->json('data.report.id');
    expect($created->json('data.report.screenshot_url'))->toBe("api/portal/projects/playinn/reports/{$reportId}/screenshot");

    // The team gets the whole context by mail, ready to reply to the client.
    Mail::assertQueued(ReportSubmittedMail::class, fn (ReportSubmittedMail $mail) => $mail->hasTo('team@nexosmart.com.ar')
        && str_starts_with($mail->mailSubject, '[URGENTE] Playinn · Playinn: No puedo cobrar')
        && $mail->envelope()->replyTo[0]->address === 'dueno@playinn.com'
        && str_contains($mail->body, 'SQLSTATE[HY000]') && str_contains($mail->body, 'HTTP 502')
        && str_contains($mail->body, "https://desk.test/monitoreo/reportes/{$reportId}") && ! str_contains($mail->body, '4111'));

    Reports::signIn($this->owner);
    $this->getJson('/api/v1/portal/projects/playinn/reports')->assertOk()->assertJsonCount(1, 'data');
    $this->get("/api/v1/portal/projects/playinn/reports/{$reportId}/screenshot")->assertOk();

    Reports::signIn($this->admin);
    $this->getJson('/api/v1/monitoring/portal-admin/reports?status=received')->assertOk()->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.client_name', 'Playinn')->assertJsonPath('data.0.user_email', 'dueno@playinn.com')
        ->assertJsonPath('data.0.has_screenshot', true);
    $detail = $this->getJson("/api/v1/monitoring/portal-admin/reports/{$reportId}")->assertOk()
        ->assertJsonCount(2, 'data.recent_errors.items')->assertJsonPath('data.recent_errors.error', null)
        ->assertJsonPath('data.recent_errors.items.0.message', 'SQLSTATE[HY000] Connection refused') // newest first
        ->assertJsonPath('data.browser.screen', '412x915')->assertJsonPath('data.browser.user_agent', 'Mozilla/5.0 (Android 14) Chrome/130')
        ->assertJsonPath('data.component_label', 'App')
        ->assertJsonPath('data.screenshot_url', "api/portal-admin/reports/{$reportId}/screenshot")
        ->assertJsonPath('data.live_check.components.1.label', 'Pagos')->assertJsonPath('data.live_check.components.1.detail', 'HTTP 502')
        ->assertJsonPath('data.live_check.components.1.kind', 'api')->assertJsonPath('data.live_check.parts.1.label', 'App')
        ->assertJsonPath('data.open_issues', []) // the monitor only confirms a failure after 10 minutes
        ->assertJsonPath('data.server_snapshot.servers', [])->assertJsonPath('data.messages', []);
    expect($detail->getContent())->not->toContain('190.1.2.3')->not->toContain('4111');

    $image = $this->get("/api/v1/monitoring/portal-admin/reports/{$reportId}/screenshot")->assertOk()
        ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($image->getContent())->toBe(Reports::png());
    expect(Report::query()->sole()->screenshot_path)->toMatch('#^reports/\d{4}/\d{2}/[0-9a-f]{32}\.png$#');

    $this->putJson("/api/v1/monitoring/portal-admin/reports/{$reportId}", ['status' => 'resolved'])->assertOk()
        ->assertJsonPath('message', 'Reporte actualizado.')->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolved_at', fn ($at) => $at !== null);
    $this->putJson("/api/v1/monitoring/portal-admin/reports/{$reportId}", ['status' => 'done'])
        ->assertUnprocessable()->assertJsonPath('errors.status.0', 'Usá received, in_review o resolved.');

    // Five valid reports per hour per client; the sixth waits.
    Reports::signIn($this->owner);
    $fields['urgency'] = 'question';
    for ($i = 0; $i < 4; $i++) {
        Reports::post('playinn', $fields)->assertCreated();
    }
    Reports::post('playinn', $fields)->assertStatus(429)
        ->assertJsonPath('message', 'Ya recibimos varios reportes en poco tiempo. Esperá un rato o escribinos por mail.');

    // After 180 days the screenshot file goes, the report stays.
    $path = Report::query()->findOrFail($reportId)->screenshot_path;
    app(PurgeReports::class)(now()->addDays(181));
    Reports::signIn($this->admin);
    $this->getJson("/api/v1/monitoring/portal-admin/reports/{$reportId}")->assertOk()->assertJsonPath('data.screenshot_url', null);
    $this->get("/api/v1/monitoring/portal-admin/reports/{$reportId}/screenshot")->assertNotFound()
        ->assertJsonPath('message', 'El reporte no tiene captura.');
    Storage::disk('local')->assertMissing($path);
});

// httpapi/report_budget_test.go TestReportBudgetSurvivesRestart (NV-08): the budget is counted on the rows.
it('counts the report budget on the stored reports: 5 an hour and 20 a day per client (CA-13.17, D-091)', function () {
    Reports::signIn($this->owner);
    for ($i = 0; $i < 5; $i++) {
        Reports::post('playinn')->assertCreated();
    }
    Reports::post('playinn')->assertStatus(429);

    // Sixteen earlier today plus four now: the day's twenty are used.
    $bankz = Fixtures::project($this->admin, 'bankz', 'Bankz');
    $other = Reports::clientUser($bankz, 'dueno@bankz.com');
    for ($i = 0; $i < 16; $i++) {
        Reports::stored($bankz, ['user_email' => 'dueno@bankz.com', 'created_at' => now()->subHours(3)]);
    }
    Reports::signIn($other);
    for ($i = 0; $i < 4; $i++) {
        Reports::post('bankz')->assertCreated();
    }
    Reports::post('bankz')->assertStatus(429);

    // Yesterday's do not count.
    $this->travel(25)->hours();
    Reports::post('bankz')->assertCreated();
});

// The coarse filter before the body is read (monitoreo attemptLimiter): any POST counts, valid or not.
it('refuses a flood of report attempts from one client before reading them', function () {
    Reports::signIn($this->owner);
    for ($i = 0; $i < 30; $i++) {
        Reports::post('playinn', ['description' => 'corto'])->assertUnprocessable();
    }
    Reports::post('playinn')->assertStatus(429)->assertJsonPath('message', 'Demasiados intentos. Esperá un rato o escribinos por mail.');
    expect(Report::query()->count())->toBe(0);
});

// httpapi/upload_quota_test.go TestClientUploadQuota (NV-24)
it('caps the images a client keeps stored at 200 MB; text and the team images still go through (CA-13.17, D-090)', function () {
    // What the client already keeps: 2500 bytes short of the quota.
    Reports::stored($this->project, ['screenshot_path' => 'reports/2026/10/old.png', 'screenshot_type' => 'image/png',
        'screenshot_size' => UploadQuota::BYTES - 2500, 'created_at' => now()->subDays(2)]);
    Reports::signIn($this->owner);

    $reportId = Reports::post('playinn', Reports::FIELDS, Reports::kilobytePng())->assertCreated()->json('data.report.id');
    $path = "/api/v1/portal/projects/playinn/reports/{$reportId}";
    Reports::message($path, 'otra captura', [Reports::kilobytePng()])->assertCreated(); // 2000 of 2500

    // A third image would pass the quota, in the chat and in a new report.
    $over = 'Llegaste al límite de imágenes guardadas (200 MB).';
    expect(Reports::message($path, 'una más', [Reports::kilobytePng()])->assertStatus(413)->json('message'))->toStartWith($over)->toContain('sin imágenes');
    expect(Reports::post('playinn', Reports::FIELDS, Reports::kilobytePng())->assertStatus(413)->json('message'))->toStartWith($over)->toContain('sin captura');

    // Text still goes through, and the team's images do not count.
    Reports::message($path, 'sin imagen')->assertCreated();
    Reports::post('playinn')->assertCreated();
    Reports::signIn($this->admin);
    Reports::message("/api/v1/monitoring/portal-admin/reports/{$reportId}", 'mirá esto', [Reports::kilobytePng()])->assertCreated();
    expect(UploadQuota::used($this->project->client_id))->toBe(UploadQuota::BYTES - 500);
});

// portal/reports_recover_test.go TestSubmitSurvivesAPanicInItsLookups (H14)
it('stores the report without an extra that failed and reports the bug (D-093)', function () {
    Exceptions::fake();
    Http::fake(fn () => throw new RuntimeException('se rompió la búsqueda'));
    $this->project->forceFill(['errors_url' => 'https://api.playinn.test/api/errors', 'errors_token' => 'token-del-proyecto'])->save();

    Reports::signIn($this->owner);
    $id = Reports::post('playinn', ['description' => 'No carga la página.', 'urgency' => 'annoying'])->assertCreated()->json('data.report.id');

    expect(Report::query()->findOrFail($id)->recent_errors)->toBeNull();
    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'se rompió la búsqueda');
});

it('records an unreadable errors endpoint instead of failing the report', function () {
    Http::fake(['https://api.playinn.test/*' => Http::response('<html>', 200)]);
    $this->project->forceFill(['errors_url' => 'https://api.playinn.test/api/errors', 'errors_token' => 'token-del-proyecto'])->save();
    Reports::signIn($this->owner);
    $id = Reports::post('playinn')->assertCreated()->json('data.report.id');

    expect(Report::query()->findOrFail($id)->recent_errors)
        ->toEqual((object) ['error' => 'la respuesta no sigue el formato de /api/errors', 'items' => []]);
});

it('sends no mail while newDesk runs next to monitoreo, but still raises ReportSubmitted (CA-13.20)', function () {
    config(['newdesk.monitoring.notify' => false]);
    Event::fake([ReportSubmitted::class]);
    Reports::signIn($this->owner);
    $id = Reports::post('playinn')->assertCreated()->json('data.report.id');

    Mail::assertNothingQueued();
    Event::assertDispatched(ReportSubmitted::class, fn (ReportSubmitted $event) => $event->report->id === $id && $event->live['parts'] === []);
});

// D-050: the project's servers as they were, and a fresh agent answer for its process components.
it('asks the agents for a fresh answer and keeps a snapshot of the project servers (D-050)', function () {
    $agent = new FakeAgentGateway;
    $agent->sections = ['pm2' => ['daemons' => [['user' => 'deploy', 'alive' => true, 'processes' => [
        ['name' => 'playinn-api', 'status' => 'online', 'restarts' => 3, 'cpu_percent' => 1.5, 'memory_bytes' => 1024, 'cwd' => '/var/www/playinn/api'],
        ['name' => 'otro-cliente', 'status' => 'online', 'cwd' => '/var/www/otro'],
    ]]]]];
    $this->app->instance(AgentGateway::class, $agent);
    $server = Fixtures::server($this->admin, 'V5');
    $server->forceFill(['status' => 'online', 'last_seen_at' => now()])->save();
    Fixtures::snapshot($server, 'metrics', FakeAgentGateway::metricsWithDisk(91));
    Fixtures::snapshot($server, 'services', ['items' => [['name' => 'nginx', 'active' => 'active', 'healthy' => true]]]);
    Fixtures::snapshot($server, 'supervisor', ['programs' => [['name' => 'playinn-worker_00', 'group' => 'playinn-worker', 'state' => 'FATAL', 'healthy' => false],
        ['name' => 'ajeno', 'state' => 'RUNNING', 'healthy' => true, 'directory' => '/var/www/otro']]]);
    ProjectFolder::query()->create(['company_id' => $this->admin->company_id, 'project_id' => $this->project->id, 'server_id' => $server->id, 'path' => '/var/www/playinn']);
    Fixtures::saved($this->project, ['kind' => 'pm2', 'label' => 'API', 'server_id' => $server->id, 'target' => 'playinn-api', 'url' => null]);
    Fixtures::saved($this->project, ['kind' => 'supervisor', 'label' => 'Colas', 'server_id' => $server->id, 'target' => 'playinn-worker', 'url' => null, 'parts' => []]);
    Alert::query()->create(['company_id' => $this->admin->company_id, 'server_id' => $server->id, 'rule' => 'disk_usage', 'target' => '/',
        'severity' => 'critical', 'state' => Alert::FIRING, 'message' => 'Disco / al 91%', 'value' => '91', 'first_seen_at' => now(), 'last_seen_at' => now()]);

    Reports::signIn($this->owner);
    $id = Reports::post('playinn')->assertCreated()
        ->assertJsonPath('data.live.components', [['label' => 'Web', 'status' => 'operational', 'phrase' => 'Funciona normalmente']])->json('data.report.id');
    // An old agent answers 404 for supervisor: that component is judged from the stored section.
    expect($agent->hits('pm2'))->toBe(1)->and($agent->hits('supervisor'))->toBe(1);

    Reports::signIn($this->admin);
    $snapshot = $this->getJson("/api/v1/monitoring/portal-admin/reports/{$id}")->assertOk()
        ->assertJsonPath('data.live_check.components.0.detail', '1 de 1 instancias online')
        ->assertJsonPath('data.live_check.components.1.status', 'down')
        ->json('data.server_snapshot.servers');
    expect($snapshot)->toHaveCount(1)
        ->and($snapshot[0])->toMatchArray(['id' => $server->id, 'name' => 'V5', 'status' => 'online'])
        ->and($snapshot[0]['metrics']['disk_percent'])->toEqual(91)
        ->and($snapshot[0]['processes'])->toBe([
            ['kind' => 'pm2', 'name' => 'playinn-api', 'state' => 'online', 'healthy' => true, 'restarts' => 3, 'cpu_percent' => 1.5, 'memory_bytes' => 1024],
            ['kind' => 'supervisor', 'name' => 'playinn-worker_00', 'state' => 'FATAL', 'healthy' => false],
        ])
        ->and($snapshot[0]['services'])->toBe([['name' => 'nginx', 'active' => 'active', 'healthy' => true]])
        ->and($snapshot[0]['alerts'][0]['message'])->toBe('Disco / al 91%');
});
