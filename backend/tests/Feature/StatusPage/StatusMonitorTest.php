<?php

use App\Domain\Clients\Actions\SaveClientUser;
use App\Domain\StatusPage\Actions\CheckComponents;
use App\Domain\StatusPage\Actions\CheckDomains;
use App\Domain\StatusPage\Actions\MeasurePageSpeed;
use App\Domain\StatusPage\Models\IncidentNote;
use App\Domain\StatusPage\Models\PageSpeedRun;
use App\Domain\StatusPage\Models\Period;
use App\Domain\StatusPage\Models\ProjectFolder;
use App\Domain\StatusPage\Notifications\StatusPageMail;
use App\Domain\StatusPage\Queries\LinkedFolders;
use App\Domain\StatusPage\Queries\ResponseTimes;
use App\Domain\StatusPage\Queries\StatusPage;
use App\Domain\StatusPage\Queries\Suggestions;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Support\StatusPageFixtures as Fixtures;

beforeEach(function () {
    $this->admin = provisionCompany('teamviolet@nexosmart.com.ar');
    config(['newdesk.monitoring.notify' => true, 'newdesk.monitoring.alert_email' => 'teamviolet@nexosmart.com.ar',
        'newdesk.frontend_url' => 'https://panel.x']);
    Mail::fake();
});

/** Each GET of the site gets the next scripted answer: 'ok' or 'broken' (no answer). */
function scriptSite(string $host, array $script): void
{
    $sequence = Http::sequence();
    foreach ($script as $step) {
        $step === 'ok' ? $sequence->push('hola', 200) : $sequence->pushFailedConnection('timeout');
    }
    Http::fake(["https://{$host}/*" => $sequence, 'https://rdap.org/*' => Http::response('', 404)]);
}

// portal/service_test.go TestMonitorRecordsPeriodsAndDomainNotices
it('records a down period from the first failure and mails the domain notices once (D-071, D-040)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 17:00:00', 'UTC'));
    scriptSite('playinn.com', ['ok', ...array_fill(0, 11, 'broken'), 'ok']);
    $project = Fixtures::project($this->admin);
    $project->forceFill(['domain' => 'playinn.com', 'domain_expires_manual' => now()->addDays(20)->toDateString()])->save();
    $component = Fixtures::saved($project, ['url' => 'https://playinn.com/']);

    for ($minute = 0; $minute < 13; $minute++) { // ok, broken ×11 (down from the 10th minute), ok
        $this->travelTo(CarbonImmutable::parse('2026-10-07 17:00:00', 'UTC')->addMinutes($minute));
        app(CheckComponents::class)->all();
        app(CheckDomains::class)();
    }

    $component->refresh();
    expect($component->status)->toBe('operational')->and($component->last_ok_at->equalTo(now()))->toBeTrue();
    $period = Period::query()->sole();
    expect($period->status)->toBe('down')
        ->and($period->started_at->toDateTimeString())->toBe('2026-10-07 17:01:00')
        ->and($period->ended_at->equalTo(now()))->toBeTrue()
        ->and(DB::table('status_checks')->where('component_id', $component->id)->count())->toBe(13);

    // 20 days left: one notice at the 30-day mark, not one every minute.
    Mail::assertQueuedCount(1);
    Mail::assertQueued(StatusPageMail::class, fn (StatusPageMail $mail) => str_contains($mail->mailSubject, 'playinn.com vence en 20 días')
        && $mail->hasTo('teamviolet@nexosmart.com.ar'));

    $this->travel(14)->days(); // 6 days left
    app(CheckDomains::class)();
    app(CheckDomains::class)();
    Mail::assertQueuedCount(2);
    Mail::assertQueued(StatusPageMail::class, fn (StatusPageMail $mail) => str_contains($mail->mailSubject, 'vence en 6 días'));

    $view = app(StatusPage::class)->view($project->fresh(), null);
    expect($view['status'])->toBe('operational')->and($view['domain']['days_left'])->toBe(6);
});

// TestDomainRenewalRestartsNotices
it('starts the domain notices over when the domain is renewed', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:00:00', 'UTC'));
    $expiry = CarbonImmutable::now()->addDays(5);
    Http::fake(['https://rdap.org/domain/playinn.app' => function () use (&$expiry) {
        return Http::response(['events' => [['eventAction' => 'registration', 'eventDate' => '2020-01-01T00:00:00Z'],
            ['eventAction' => 'expiration', 'eventDate' => $expiry->toIso8601ZuluString()]]]);
    }]);
    Fixtures::project($this->admin)->forceFill(['domain' => 'playinn.app'])->save();

    app(CheckDomains::class)(); // 5 days left: the 7-day notice
    $expiry = CarbonImmutable::now()->addYear();
    $this->travel(25)->hours();
    app(CheckDomains::class)(); // renewed: nothing to say for a year
    $this->travelTo($expiry->subDays(20));
    app(CheckDomains::class)(); // 20 days left of the new term

    $subjects = [];
    Mail::assertQueued(StatusPageMail::class, function (StatusPageMail $mail) use (&$subjects) {
        $subjects[] = $mail->mailSubject;

        return true;
    });
    expect($subjects)->toHaveCount(2)
        ->and($subjects[0])->toContain('vence en 5 días')
        ->and($subjects[1])->toContain('vence en 20 días');
});

// TestBackNoticeToClientWithoutDuration (D-063, CA-13.16)
it('mails each client user that it works again, without when or how long (CA-13.16)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 17:00:00', 'UTC'));
    scriptSite('acme.test', ['ok', 'broken', 'broken', 'ok', ...array_fill(0, 11, 'broken'), 'ok']);
    $project = Fixtures::project($this->admin, 'acme', 'Acme');
    foreach (['uno@acme.test', 'dos@acme.test'] as $email) {
        app(SaveClientUser::class)->create($project->client, $email, $this->admin);
    }
    $component = Fixtures::saved($project, ['url' => 'https://acme.test/']);
    $tick = function () {
        app(CheckComponents::class)->all();
        $this->travel(1)->minutes();
    };

    foreach (range(1, 4) as $_) { // ok, broken ×2, ok: a short cut
        $tick();
    }
    expect(Period::query()->count())->toBe(0);
    Mail::assertNothingQueued();
    foreach (range(1, 11) as $_) {
        $tick();
    }
    expect($component->fresh()->status)->toBe('down');
    Mail::assertNothingQueued(); // the outage itself is never mailed

    $tick(); // back
    $mails = [];
    Mail::assertQueued(StatusPageMail::class, function (StatusPageMail $mail) use (&$mails) {
        $mails[] = $mail;

        return true;
    });
    expect($mails)->toHaveCount(2)
        ->and($mails[0]->to)->toHaveCount(1)
        ->and($mails[0]->to[0]['address'])->not->toBe($mails[1]->to[0]['address'])
        ->and($mails[0]->mailSubject)->toBe('Acme: Web de Acme ya funciona con normalidad.')
        ->and($mails[0]->body)->toContain('https://panel.x/portal/acme');
    foreach (['min', '17:', '14:', 'desde', 'duró', 'estuvo', 'caíd'] as $leak) {
        expect(mb_strtolower($mails[0]->mailSubject.$mails[0]->body))->not->toContain($leak);
    }
});

it('sends nothing while newDesk runs next to monitoreo (CA-13.20)', function () {
    config(['newdesk.monitoring.notify' => false]);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 17:00:00', 'UTC'));
    scriptSite('acme.test', [...array_fill(0, 12, 'broken'), 'ok']);
    $project = Fixtures::project($this->admin, 'acme', 'Acme');
    $project->forceFill(['domain' => 'acme.test', 'domain_expires_manual' => now()->addDays(3)->toDateString()])->save();
    app(SaveClientUser::class)->create($project->client, 'uno@acme.test', $this->admin);
    Fixtures::saved($project, ['url' => 'https://acme.test/']);

    foreach (range(1, 13) as $_) {
        app(CheckComponents::class)->all();
        app(CheckDomains::class)();
        $this->travel(1)->minutes();
    }

    expect(Period::query()->sole()->ended_at)->not->toBeNull();
    Mail::assertNothingQueued();
    expect($project->fresh()->domain_notice_level)->toBe(0); // the notice waits for the cutover
});

// portal/owner_test.go TestLegacyComponentPinsUniqueOwner (D-089)
it('pins a pm2 component to the only user running its app', function () {
    $project = Fixtures::project($this->admin);
    $server = Fixtures::server($this->admin);
    $savePm2 = fn (array ...$daemons) => Fixtures::snapshot($server, 'pm2', ['daemons' => $daemons]);
    $component = fn (string $target) => Fixtures::saved($project, ['kind' => 'pm2', 'label' => $target, 'url' => null, 'server_id' => $server->id, 'target' => $target]);
    [$api, $shared] = [$component('api-b'), $component('shared')];
    $savePm2(
        ['user' => 'clientb', 'processes' => [['name' => 'api-b', 'status' => 'online'], ['name' => 'shared', 'status' => 'online']]],
        ['user' => 'eve', 'processes' => [['name' => 'shared', 'status' => 'online']]],
    );

    $checked = app(CheckComponents::class)->one($api);
    expect($checked->target_owner)->toBe('clientb')->and($checked->detail)->toBe('1 de 1 instancias online');
    $checked = app(CheckComponents::class)->one($shared);
    expect($checked->target_owner)->toBeNull()->and($checked->detail)->toBe('hay apps con este nombre en varios usuarios: elegí el usuario');

    // Pinned, another user's copy no longer counts.
    $savePm2(['user' => 'clientb', 'processes' => [['name' => 'api-b', 'status' => 'stopped']]],
        ['user' => 'eve', 'processes' => [['pm_id' => 7, 'name' => 'api-b', 'status' => 'online']]]);
    expect(app(CheckComponents::class)->one($api->fresh())->detail)->toBe('0 de 1 instancias online');
});

it('leaves agent components unknown without a fresh snapshot or with the server disabled (D-039)', function () {
    $project = Fixtures::project($this->admin);
    $server = Fixtures::server($this->admin);
    Fixtures::snapshot($server, 'supervisor', ['programs' => [['name' => 'queue', 'state' => 'RUNNING']]]);
    $workers = Fixtures::saved($project, ['kind' => 'supervisor', 'url' => null, 'server_id' => $server->id, 'target' => 'queue']);

    expect(app(CheckComponents::class)->one($workers)->status)->toBe('operational');
    $this->travel(6)->minutes();
    expect(app(CheckComponents::class)->one($workers))->status->toBe('unknown')->detail->toBe('sin dato reciente del agente (supervisor)');
    $server->forceFill(['enabled' => false])->save();
    expect(app(CheckComponents::class)->one($workers)->detail)->toBe('el server no existe o está deshabilitado');
});

// portal/history_test.go TestResponseTimesBucketsAndIncidentNotes
it('buckets response times by 5 minutes and hides note authors from the client (D-042)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 15:00:00', 'UTC'));
    $project = Fixtures::project($this->admin);
    $site = Fixtures::saved($project, ['url' => 'https://playinn.com']);
    foreach ([100, 300, null, 50] as $i => $latency) {
        DB::table('status_checks')->insert(['company_id' => $project->company_id, 'component_id' => $site->id,
            'checked_at' => now()->subMinutes(30)->addMinutes(2 * $i), 'ok' => $latency !== null, 'latency_ms' => $latency]);
    }

    $times = app(ResponseTimes::class)($project, '24h');
    expect($times['resolution'])->toBe('5m')
        ->and($times['series'][0]['points'])->toHaveCount(2)
        ->and($times['series'][0]['points'][0])->toMatchArray(['ts' => '2026-10-07T14:30:00Z', 'avg_ms' => 200, 'max_ms' => 300])
        ->and($times['series'][0]['points'][1]['avg_ms'])->toBe(50);

    $period = Period::query()->create(['company_id' => $project->company_id, 'component_id' => $site->id, 'status' => 'down', 'started_at' => now()->subHours(2)]);
    $components = StatusPage::components($project);
    $since = CarbonImmutable::now()->subDays(30);
    $incidents = StatusPage::incidents($components, $since, 20, forTeam: false);
    expect($incidents)->toHaveCount(1)->and($incidents[0]['status'])->toBe('ongoing');

    IncidentNote::query()->create(['company_id' => $project->company_id, 'period_id' => $period->id, 'user_id' => $this->admin->id,
        'author' => $this->admin->email, 'body' => 'Investigando: el proveedor de pagos no responde.', 'created_at' => now()]);
    $forClient = StatusPage::incidents($components, $since, 20, forTeam: false);
    $forTeam = StatusPage::incidents($components, $since, 20, forTeam: true);
    expect($forClient[0]['notes'])->toHaveCount(1)->and($forClient[0]['notes'][0])->not->toHaveKey('author')
        ->and($forTeam[0]['notes'][0]['author'])->toBe('teamviolet@nexosmart.com.ar');
});

// TestPageSpeedWeeklyRuns (D-043)
it('measures each web component weekly, keeping the last good run over a failed one (D-043)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 15:00:00', 'UTC'));
    $project = Fixtures::project($this->admin);
    $site = Fixtures::saved($project, ['url' => 'https://playinn.com']);
    $quota = false;
    Http::fake([MeasurePageSpeed::ENDPOINT.'*' => function (Request $request) use (&$quota) {
        expect($request['url'])->toBe('https://playinn.com');
        if ($quota) {
            return Http::response(['error' => ['message' => 'Quota exceeded']], 429);
        }

        return $request['strategy'] === 'mobile'
            ? Http::response(['loadingExperience' => ['metrics' => ['LARGEST_CONTENTFUL_PAINT_MS' => ['percentile' => 2300],
                'INTERACTION_TO_NEXT_PAINT' => ['percentile' => 180], 'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['percentile' => 7]]],
                'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.71]], 'audits' => ['total-blocking-time' => ['numericValue' => 420.4]]]])
            : Http::response(['loadingExperience' => [], 'lighthouseResult' => ['categories' => ['performance' => ['score' => 0.93]],
                'audits' => ['largest-contentful-paint' => ['numericValue' => 1210.6], 'cumulative-layout-shift' => ['numericValue' => 0.01],
                    'first-contentful-paint' => ['numericValue' => 800], 'total-blocking-time' => ['numericValue' => 30]]]]);
    }]);

    app(MeasurePageSpeed::class)->due();
    app(MeasurePageSpeed::class)->due(); // nothing is due again for a week
    Http::assertSentCount(2);

    $summaries = StatusPage::pageSpeedSummaries(StatusPage::components($project), now()->toImmutable());
    expect($summaries)->toHaveCount(1)
        ->and($summaries[0]['mobile'])->toMatchArray(['field' => true, 'score' => 71, 'lcp_ms' => 2300, 'inp_ms' => 180, 'cls' => 0.07])
        ->and($summaries[0]['desktop'])->toMatchArray(['field' => false, 'score' => 93, 'lcp_ms' => 1211, 'inp_ms' => null]);

    $this->travel(8)->days();
    $quota = true;
    app(MeasurePageSpeed::class)->due();
    $failed = PageSpeedRun::query()->where('measured_at', '>=', now()->subHour())->orderBy('id')->get();
    expect($failed)->toHaveCount(2)->and($failed[0]->error)->toContain('Quota exceeded')
        ->and(StatusPage::pageSpeedSummaries(StatusPage::components($project), now()->toImmutable())[0]['mobile']['score'])->toBe(71);
});

// portal/pagespeed_test.go TestPageSpeedKeyInHeader (D-093 H4)
it('sends the PageSpeed key in a header and never stores or logs it', function () {
    $key = 'AIzaSy-secret-psi-key';
    config(['newdesk.monitoring.pagespeed_api_key' => $key]);
    Log::spy();
    $site = Fixtures::saved(Fixtures::project($this->admin), ['url' => 'https://playinn.com']);
    Http::fake([MeasurePageSpeed::ENDPOINT.'*' => Http::sequence()
        ->push(['error' => ['message' => "API key not valid: {$key}"]], 400)
        ->pushFailedConnection("cURL error 7: Failed to connect, key {$key}")]);

    app(MeasurePageSpeed::class)->measure($site, 'mobile');
    app(MeasurePageSpeed::class)->measure($site, 'desktop');

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', $key) && ! str_contains($request->url(), $key));
    $runs = PageSpeedRun::query()->get();
    expect($runs)->toHaveCount(2);
    foreach ($runs as $run) {
        expect($run->error)->not->toBeNull()->not->toContain($key)->toContain('[key]');
    }
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => ! str_contains(json_encode($context), $key));
});

// TestFoldersTypesFollowWhatRuns (D-123)
it('types a linked folder by what each server serves or runs from it (D-072, D-123)', function () {
    $project = Fixtures::project($this->admin);
    $repo = '/var/www/playinn';
    $monorepo = ['items' => [['path' => $repo, 'name' => 'playinn', 'type' => 'monorepo', 'subapps' => [
        ['path' => 'admin', 'type' => 'react'], ['path' => 'back', 'type' => 'laravel'], ['path' => 'scripts', 'type' => 'node'], ['path' => 'web', 'type' => 'nextjs']]]]];
    $signals = [
        'A' => ['sites' => ['items' => [['server_names' => ['admin.playinn.app'], 'projects' => ["{$repo}/admin"]]]],
            'pm2' => ['daemons' => [['processes' => [['name' => 'playinn-web', 'cwd' => "{$repo}/web"], ['name' => 'pm2-logrotate', 'cwd' => "{$repo}/scripts", 'is_module' => true]]]]]],
        'B' => ['sites' => ['items' => [['server_names' => ['api.playinn.app'], 'projects' => ["{$repo}/back"]]]],
            'supervisor' => ['programs' => [['name' => 'playinn-worker', 'project' => "{$repo}/back"]]]],
        'C' => [],
    ];
    foreach ($signals as $name => $sections) {
        $server = Fixtures::server($this->admin, $name);
        foreach (['projects' => $monorepo, ...$sections] as $section => $data) {
            Fixtures::snapshot($server, $section, $data);
        }
        ProjectFolder::query()->create(['company_id' => $project->company_id, 'project_id' => $project->id, 'server_id' => $server->id, 'path' => $repo]);
    }

    $types = array_column(app(LinkedFolders::class)->forProject($project), 'types', 'server_name');
    expect($types)->toBe([
        'A' => ['monorepo', 'react', 'nextjs'],
        'B' => ['monorepo', 'laravel'],
        'C' => ['monorepo', 'react', 'laravel', 'node', 'nextjs'],
    ]);
});

// portal/suggest_test.go TestSuggestionsFromLinkedFolders (D-044)
it('suggests components from what serves and runs from the linked folders (D-044)', function () {
    $project = Fixtures::project($this->admin);
    $server = Fixtures::server($this->admin);
    [$front, $api] = ['/var/www/playinn/front', '/var/www/playinn/api'];
    Fixtures::snapshot($server, 'projects', ['items' => [['path' => $front, 'name' => 'front'], ['path' => $api, 'name' => 'api']]]);
    Fixtures::snapshot($server, 'sites', ['items' => [
        ['server_names' => ['playinn.com'], 'redirects' => ['www.playinn.com'], 'projects' => [$front]],
        ['server_names' => ['api.playinn.com'], 'projects' => ["{$api}/public"]],
        ['server_names' => ['cms.playinn.com'], 'projects' => ["{$api}/public"]],
        ['server_names' => ['compartido.com'], 'projects' => [$front, '/var/www/a', '/var/www/b']], // a shared vhost is nobody's site
    ]]);
    Fixtures::snapshot($server, 'pm2', ['daemons' => [['processes' => [['name' => 'playinn-web', 'cwd' => $front],
        ['name' => 'pm2-logrotate', 'cwd' => '/root/.pm2/modules', 'is_module' => true]]]]]);
    Fixtures::snapshot($server, 'supervisor', ['programs' => [
        ['name' => 'playinn-queue:playinn-queue_00', 'group' => 'playinn-queue', 'project' => $api],
        ['name' => 'playinn-queue:playinn-queue_01', 'group' => 'playinn-queue', 'project' => $api],
        ['name' => 'otro-worker', 'project' => '/var/www/otro']]]);
    Fixtures::snapshot($server, 'cron', ['timers' => [['unit' => 'backup-playinn.timer', 'custom' => true], ['unit' => 'apt-daily.timer'],
        ['unit' => 'backup-otro.timer', 'custom' => true]]]);
    foreach ([$front, $api] as $path) {
        ProjectFolder::query()->create(['company_id' => $project->company_id, 'project_id' => $project->id, 'server_id' => $server->id, 'path' => $path]);
    }
    Fixtures::saved($project, ['label' => 'web', 'parts' => [], 'url' => 'https://playinn.com/']);

    $got = collect(app(Suggestions::class)($project))->keyBy(fn (array $suggestion) => $suggestion['kind'].' '.($suggestion['url'] ?? $suggestion['target']));
    expect($got->keys()->sort()->values()->all())->toBe(collect(['http https://playinn.com/', 'http https://cms.playinn.com/',
        'health https://api.playinn.com/health', 'pm2 playinn-web', 'supervisor playinn-queue', 'timer backup-playinn.timer'])->sort()->values()->all())
        ->and($got['http https://playinn.com/'])->toMatchArray(['exists' => true, 'label' => 'Web'])
        ->and($got['health https://api.playinn.com/health'])->toMatchArray(['exists' => false, 'label' => 'API', 'parts' => ['web', 'cms']])
        ->and($got['http https://cms.playinn.com/'])->toMatchArray(['label' => 'CMS', 'parts' => ['cms']])
        ->and($got['supervisor playinn-queue']['parts'])->toBe(['web', 'cms'])
        ->and($got['timer backup-playinn.timer']['parts'])->toBe([]);
});
