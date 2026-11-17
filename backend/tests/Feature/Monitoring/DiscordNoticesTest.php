<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Actions\ApplyAlertResults;
use App\Domain\Monitoring\Actions\SendAlertDigest;
use App\Domain\Monitoring\Actions\SendHeartbeat;
use App\Domain\Monitoring\Actions\WatchProjectErrors;
use App\Domain\Monitoring\Alerts\AlertObservation;
use App\Domain\Monitoring\Alerts\AlertResult;
use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Discord\Notifier;
use App\Domain\Monitoring\Events\AlertChanged;
use App\Domain\Monitoring\Events\VulnerabilitiesScanned;
use App\Domain\Monitoring\Polling\PollerSignals;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\ProjectSlug;
use App\Domain\StatusPage\Models\ProjectFolder;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeDiscord;
use Tests\Support\StatusPageFixtures as Fixtures;

/**
 * Port of monitoreo's portal/discord_test.go and errorwatch_test.go: the notices each project's
 * channel and #resumen get (RF-13.09, D-054, D-059, D-061, D-073, D-078, D-107), against the real
 * database and a fake Discord. The queue is sync in tests: listeners and messages run in place.
 */
const PLAYINN_CHANNEL = '1000000000000000101';

const OTRO_CHANNEL = '1000000000000000102';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['newdesk.monitoring.notify' => true, 'newdesk.frontend_url' => 'https://panel.test']);
    $this->discord = FakeDiscord::install();
    $admin = adminUser('admin@nexosmart.com.ar');
    $this->companyId = $admin->company_id;
    $this->server = Fixtures::server($admin, 'Servidor 5');

    $lead = fn (string $email, ?string $discordId) => User::factory()->forRole(roleNamed($admin, DefaultRoles::DEVELOPER))
        ->create(['email' => $email, 'discord_user_id' => $discordId]);
    $project = function (string $slug, ?string $channel, User $leader) use ($admin) {
        $project = Fixtures::project($admin, $slug, 'Varios');
        $project->forceFill(['discord_channel_id' => $channel, 'leader_user_id' => $leader->id])->save();
        ProjectFolder::query()->create(['company_id' => $admin->company_id, 'project_id' => $project->id, 'server_id' => $this->server->id,
            'path' => "/var/www/{$slug}"]);

        return $project;
    };
    $this->playinn = $project('playinn', PLAYINN_CHANNEL, $lead('lead@nexosmart.com.ar', '123456789012345678'));
    $this->otro = $project('otro', OTRO_CHANNEL, $lead('tomi@nexosmart.com.ar', null));
    $this->silent = $project('silent', null, $lead('juan@nexosmart.com.ar', '223456789012345678'));
    Fixtures::saved($this->playinn, ['url' => 'https://playinn.app/']);

    $this->now = CarbonImmutable::now()->utc()->startOfSecond();
    $this->apply = fn (array $results, CarbonImmutable $at) => app(ApplyAlertResults::class)($this->server, $results, $at);
});

/** What reached Discord since the last call, by the name of its channel. @return list<array{channel: string, content: string, mentions: list<string>}> */
function discordPosts(FakeDiscord $discord): array
{
    $names = [PLAYINN_CHANNEL => 'playinn', OTRO_CHANNEL => 'otro', FakeDiscord::SUMMARY => 'resumen', FakeDiscord::CREATED => 'nuevo'];

    return array_map(fn (array $post) => ['channel' => $names[$post['channel']] ?? $post['channel'], 'content' => $post['content'],
        'mentions' => $post['mentions']], $discord->take());
}

/** monitoreo's store.Alert JSON, as AlertChanged carries it. */
function alertPayload(object $test, array $attributes): array
{
    return ['id' => null, 'server_id' => $test->server->id, 'server_name' => 'Servidor 5', 'target' => '', 'severity' => AlertRules::CRITICAL,
        'state' => 'firing', 'value' => '', 'first_seen_at' => $test->now->toIso8601ZuluString(), 'last_seen_at' => $test->now->toIso8601ZuluString(),
        'resolved_at' => null, 'last_notified_at' => null, ...$attributes];
}

function noticeOf(object $test, array $attributes, string $change = AlertChanged::OPENED): void
{
    event(new AlertChanged($test->companyId, alertPayload($test, $attributes), $change));
}

// TestAlertNoticesGoToTheirProjectsChannel
it('sends each alert to the channel of its project, and a server down to #resumen only (D-061)', function () {
    Fixtures::snapshot($this->server, 'pm2', ['daemons' => [['user' => 'deploy', 'processes' => [['id' => 3, 'name' => 'otro-api', 'cwd' => '/var/www/otro/api']]]]]);
    Fixtures::snapshot($this->server, 'supervisor', ['programs' => [['name' => 'queue:queue_00', 'group' => 'queue', 'directory' => '/var/www/playinn/api']]]);

    // Through the engine: the server going down names every project on it and pings their leads.
    ($this->apply)(AlertRules::serverStatus('down', 22), $this->now);
    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe('resumen')
        ->and($posts[0]['content'])->toContain('🔴 **Servidor 5** · El servidor no responde', 'Playinn', 'Otro', 'Silent', '<@123456789012345678>',
            '<@223456789012345678>', 'tomi\@nexosmart.com.ar (sin Discord cargado)', "<https://panel.test/monitoreo/servers/{$this->server->id}>")
        ->and($posts[0]['mentions'])->toHaveCount(2);

    // A site down while its server is down is covered by the server notice.
    $siteDown = ['id' => 9001, 'rule' => AlertRules::SITE_DOWN, 'target' => 'www.playinn.app', 'message' => 'El sitio www.playinn.app responde HTTP 502'];
    noticeOf($this, $siteDown);
    expect(discordPosts($this->discord))->toBe([]);

    ($this->apply)(AlertRules::serverStatus('online', 22), $this->now->addMinutes(12));
    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe('resumen')
        ->and($posts[0]['content'])->toContain('Resuelto', 'duró 12 min')->and($posts[0]['mentions'])->toBe([]);

    // Its opening was never posted, so neither is its end.
    noticeOf($this, [...$siteDown, 'state' => 'resolved', 'resolved_at' => $this->now->addMinutes(13)->toIso8601ZuluString()], AlertChanged::RESOLVED);
    expect(discordPosts($this->discord))->toBe([]);

    // Each alert goes only to the project it is about. [alert, channel ('' = none), pings]
    $cases = [
        [['rule' => AlertRules::SITE_DOWN, 'target' => 'www.playinn.app', 'message' => 'El sitio responde HTTP 502'], 'playinn', true],
        [['rule' => AlertRules::SSL_EXPIRING, 'target' => 'playinn.app', 'severity' => AlertRules::WARNING, 'message' => 'El certificado vence en 10 días'], 'playinn', false],
        [['rule' => AlertRules::PM2_STATUS, 'target' => 'otro-api#3@deploy', 'message' => 'PM2 otro-api en estado errored'], 'otro', true],
        [['rule' => AlertRules::SUPERVISOR, 'target' => 'queue:queue_00', 'message' => 'Supervisor queue caído'], 'playinn', true],
        [['rule' => AlertRules::CPU, 'target' => '', 'message' => 'CPU al 99%'], '', false],
        [['rule' => AlertRules::SITE_DOWN, 'target' => 'otrocliente.com', 'message' => 'El sitio responde HTTP 502'], '', false],
    ];
    foreach ($cases as $i => [$alert, $channel, $pings]) {
        noticeOf($this, ['id' => 100 + $i, ...$alert]);
        $posts = discordPosts($this->discord);
        if ($channel === '') {
            expect($posts)->toBe([], "{$alert['rule']} {$alert['target']}");

            continue;
        }
        $pinged = count($posts) === 1 && ($posts[0]['mentions'] !== [] || str_contains($posts[0]['content'], '(sin Discord cargado)'));
        expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe($channel)->and($pinged)->toBe($pings, "{$alert['rule']} {$alert['target']}")
            ->and($posts[0]['content'])->toContain('\(server Servidor 5\)', '<https://panel.test/monitoreo/clientes/');
    }

    // A warning that turns critical pings.
    noticeOf($this, ['id' => 101, ...$cases[1][0], 'severity' => AlertRules::CRITICAL], AlertChanged::ESCALATED);
    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['mentions'])->toHaveCount(1)->and($posts[0]['content'])->toContain('ahora es crítica');
});

// TestServerNoticesGoToTheSummaryChannel
it('sends a service or the agent down to #resumen, and CPU, memory and disk only to the digest (D-061)', function () {
    noticeOf($this, ['id' => 501, 'rule' => AlertRules::SERVICE, 'target' => 'nginx', 'message' => 'El servicio nginx está caído']);
    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe('resumen')->and($posts[0]['mentions'])->toHaveCount(2)
        ->and($posts[0]['content'])->toContain('nginx');

    noticeOf($this, ['id' => 502, 'rule' => AlertRules::AGENT_DOWN, 'severity' => AlertRules::WARNING, 'message' => 'El agente no responde']);
    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe('resumen')->and($posts[0]['mentions'])->toBe([])
        ->and($posts[0]['content'])->toContain('Proyectos: ');

    foreach ([AlertRules::CPU, AlertRules::MEMORY, AlertRules::DISK] as $i => $rule) {
        noticeOf($this, ['id' => 600 + $i, 'rule' => $rule, 'message' => $rule]);
    }
    expect(discordPosts($this->discord))->toBe([]);

    app(Notifier::class)->summary('📋 **Resumen de 9 a 18 h**');
    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe('resumen')->and($posts[0]['mentions'])->toBe([]);

    // Without #resumen, server notices are not posted anywhere.
    config(['newdesk.monitoring.discord.summary_channel_id' => null]);
    noticeOf($this, ['id' => 503, 'rule' => AlertRules::SERVICE, 'target' => 'nginx', 'message' => 'El servicio nginx está caído']);
    app(Notifier::class)->summary('x');
    expect(discordPosts($this->discord))->toBe([]);
});

it('summarizes a flood of openings in #resumen, and none of its alerts posts alone (D-107, CA-13.11)', function () {
    // 30 apps running from Playinn's folder: each one alone would go to its channel.
    $processes = array_map(fn (int $i) => ['id' => $i, 'name' => "playinn-{$i}", 'cwd' => '/var/www/playinn/app'], range(0, 29));
    Fixtures::snapshot($this->server, 'pm2', ['daemons' => [['user' => 'deploy', 'processes' => $processes]]]);
    $failing = array_map(fn (int $i) => new AlertObservation("playinn-{$i}#{$i}@deploy", true, 'critical', "PM2 playinn-{$i} en estado errored", 'errored'), range(0, 29));
    ($this->apply)([new AlertResult(AlertRules::PM2_STATUS, 0, 1, $failing)], $this->now);

    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe('resumen')
        ->and($posts[0]['content'])->toContain('30 alertas pm2\_process nuevas a la vez');
});

// TestVulnerabilityNoticesTellWhatIsActive (D-073)
it('posts the active vulnerabilities of each new scan, marking the new ones and counting the fixed ones (D-073)', function () {
    $guzzle = ['package' => 'guzzlehttp/guzzle', 'severity' => 'high', 'title' => 'Header injection', 'cve' => 'CVE-2026-0001'];
    $laravel = ['package' => 'laravel/framework', 'severity' => 'critical', 'title' => 'RCE', 'cve' => 'CVE-2026-0002'];
    $axios = ['package' => 'axios', 'severity' => 'high', 'title' => 'SSRF'];
    $dir = fn (string $path, string $manager, array $findings, string $error = '') => ['path' => $path, 'manager' => $manager, 'error' => $error, 'findings' => $findings];
    $scan = fn (string $at, array $items) => ['scanned_at' => $at, 'items' => $items];
    $scanned = fn (array $previous, array $next) => event(new VulnerabilitiesScanned($this->companyId, $this->server->id, 'Servidor 5', $previous, $next));

    $previous = $scan('2026-10-14T08:05:00Z', [$dir('/var/www/playinn/api', 'composer', [$guzzle]), $dir('/var/www/playinn/web', 'npm', [$axios]),
        $dir('/var/www/otro', 'npm', [], 'npm audit no devolvió JSON'), $dir('/var/www/silent', 'npm', [])]);
    $next = $scan('2026-10-15T08:05:00Z', [$dir('/var/www/playinn/api', 'composer', [$guzzle, $laravel]), $dir('/var/www/playinn/web', 'npm', []),
        $dir('/var/www/playinn/nuevo', 'npm', [$axios]), // first audit: the baseline
        $dir('/var/www/otro', 'npm', [$axios]),          // failed yesterday
        $dir('/var/www/silent', 'npm', [$laravel])]);    // no channel
    $scanned($previous, $next);

    $posts = collect(discordPosts($this->discord))->keyBy('channel');
    expect($posts->keys()->sort()->values()->all())->toBe(['otro', 'playinn'])
        ->and($posts['playinn']['content'])->toContain('3 vulnerabilidades activas para fixear (1 critical · 2 high)',
            'laravel/framework — critical · CVE-2026-0002 · nueva', "guzzlehttp/guzzle — high · CVE-2026-0001\n", '/var/www/playinn/nuevo',
            'Nuevas: 1.', 'Se resolvió 1.', '<@123456789012345678>')
        ->and($posts['playinn']['content'])->not->toContain('axios — high · nueva') // a first-time folder is not new
        ->and($posts['otro']['content'])->toContain('1 vulnerabilidad activa para fixear (1 high)', 'tomi\@nexosmart.com.ar (sin Discord cargado)')
        ->and($posts['otro']['mentions'])->toBe([]);

    // The same scan fetched again, or the first scan of a server, says nothing.
    $scanned($next, $next);
    $scanned([], $next);
    expect(discordPosts($this->discord))->toBe([]);

    // Nothing new but one left: it pings again.
    $remaining = $scan('2026-10-16T08:05:00Z', [$dir('/var/www/playinn/api', 'composer', [$guzzle]), $dir('/var/www/playinn/web', 'npm', [])]);
    $scanned($next, $remaining);
    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['mentions'])->toHaveCount(1)
        ->and($posts[0]['content'])->toContain('1 vulnerabilidad activa para fixear (1 high)', 'Se resolvió 1.')->not->toContain('Nuevas');

    // All fixed: a quiet note.
    $scanned($remaining, $scan('2026-10-17T08:05:00Z', [$dir('/var/www/playinn/api', 'composer', []), $dir('/var/www/playinn/web', 'npm', [])]));
    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['mentions'])->toBe([])
        ->and($posts[0]['content'])->toContain('Se resolvió 1 vulnerabilidad de dependencias', 'Ya no quedan activas.');
});

// TestErrorWatchPostsOnlyNewErrors (D-054)
it('posts only the new API errors, pings once an hour and pauses a rejected token (D-054)', function () {
    $api = ['status' => 200, 'date' => '2026-10-15', 'entries' => [], 'calls' => 0];
    Http::fake(['https://api.playinn.test/api/errors' => function () use (&$api) {
        $api['calls']++;

        return $api['status'] !== 200 ? Http::response('', $api['status'])
            : Http::response(['date' => $api['date'], 'total' => count($api['entries']), 'errors' => $api['entries']]);
    }]);
    $entry = fn (string $at, string $level, string $message) => ['timestamp' => "2026-10-15 {$at}", 'level' => $level, 'message' => $message, 'action' => 'x',
        'method' => 'POST', 'url' => 'https://api.playinn.app/pagos', 'user_id' => null, 'ip' => '1.1.1.1', 'input' => null,
        'exception_class' => 'Illuminate\Database\QueryException', 'file' => null];
    $this->playinn->forceFill(['errors_url' => 'https://api.playinn.test/api/errors', 'errors_token' => 'token'])->save();
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));
    $check = function (int $seconds) {
        $this->travel($seconds)->seconds();
        app(WatchProjectErrors::class)();

        return discordPosts($this->discord);
    };

    $api['entries'] = [$entry('09:00:00', 'ERROR', 'viejo')];
    expect($check(0))->toBe([]); // the first read is the baseline

    array_push($api['entries'], $entry('12:01:00', 'ERROR', 'SQLSTATE[HY000] Connection refused'), $entry('12:02:00', 'ERROR', 'SQLSTATE[HY000] Connection refused'),
        $entry('12:03:00', 'ERROR', 'Undefined index: total'), $entry('12:03:30', 'WARNING', 'slow query'));
    $posts = $check(300);
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe('playinn')->and($posts[0]['mentions'])->toHaveCount(1)
        ->and($posts[0]['content'])->toContain('3 errores nuevos en la API (2 distintos)', 'QueryException: SQLSTATE\[HY000\] Connection refused ×2')
        ->not->toContain('slow')->not->toContain('viejo');
    expect($check(300))->toBe([]);

    $api['entries'][] = $entry('12:20:00', 'ERROR', 'otro más');
    $posts = $check(300);
    expect($posts)->toHaveCount(1)->and($posts[0]['mentions'])->toBe([])->and($posts[0]['content'])->toContain('1 error nuevo'); // a burst pings once an hour

    $api['entries'][] = $entry('13:30:00', 'ERROR', 'una hora después');
    $posts = $check(3600);
    expect($posts)->toHaveCount(1)->and($posts[0]['mentions'])->toHaveCount(1);

    // A new day: what comes is new, without a baseline.
    [$api['date'], $api['entries']] = ['2026-10-16', [$entry('00:01:00', 'ERROR', 'pasada la medianoche')]];
    $posts = $check(300);
    expect($posts)->toHaveCount(1)->and($posts[0]['content'])->toContain('pasada la medianoche');

    // A rejected token pauses the project for hours: the endpoint bans IPs.
    [$api['status'], $api['calls']] = [401, 0];
    $check(300);
    $check(300);
    $check(5 * 3600);
    expect($api['calls'])->toBe(1);
    $check(3600);
    expect($api['calls'])->toBe(2);
});

// errorwatch_test.go TestErrorsMessageClassOneLine (H17)
it('keeps an exception class on one line and within its label', function () {
    $class = "App\\Exceptions\\Boom\n\n**Pagá acá**\n".str_repeat('x', 200);
    $notifier = app(Notifier::class);
    $message = $notifier->errorsMessage($notifier->channelOf($this->playinn), [['level' => 'ERROR', 'message' => 'falló', 'exception_class' => $class]], false);

    $lines = explode("\n", $message->content);
    expect($lines)->toHaveCount(3)->and($lines[1])->toBe('• Boom \*\*Pagá acá\*\* '.str_repeat('x', 62).'…: falló'); // 80 characters of class
});

// TestCreateChannelForANewProject (D-059)
it('creates the channel of a new project in the category, with a first message for its lead', function () {
    $id = app(Notifier::class)->createChannel($this->silent);

    expect($id)->toBe(FakeDiscord::CREATED)
        ->and($this->discord->created)->toHaveCount(1)
        ->and($this->discord->created[0])->toMatchArray(['name' => 'monitoreo-silent', 'parent_id' => FakeDiscord::CATEGORY])
        ->and($this->discord->created[0]['topic'])->toContain('Silent')
        ->and($this->silent->fresh()->discord_channel_id)->toBe(FakeDiscord::CREATED);
    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe('nuevo')->and($posts[0]['mentions'])->toBe(['223456789012345678'])
        ->and($posts[0]['content'])->toContain('<@223456789012345678>', 'avisos del proyecto', '<#'.FakeDiscord::SUMMARY.'>');
});

// TestNoLeadPingsNobody
it('pings nobody when there is no lead, and names channels after the project', function () {
    $message = Notifier::compose(FakeDiscord::SUMMARY, '🔴', 'Reaquila', 'El sitio responde HTTP 502', [], [], true, 'https://panel.test/p');

    expect($message->mention)->toBe([])
        ->and($message->content)->toBe("🔴 **Reaquila** · El sitio responde HTTP 502\n<https://panel.test/p>")
        ->and('monitoreo-'.ProjectSlug::slugify('Droguería Sur'))->toBe('monitoreo-drogueria-sur');
});

// TestAlertNoticeEscapesAgentNames (NV-13, D-078)
it('posts the names a host user picks as plain text, on one line (CA-13.21)', function () {
    $name = "[Reautenticar](https://example.invalid/login)\n# x";
    Fixtures::snapshot($this->server, 'pm2', ['daemons' => [['user' => 'deploy', 'processes' => [['id' => 1, 'name' => $name, 'cwd' => '/var/www/playinn/app']]]]]);
    noticeOf($this, ['id' => 700, 'rule' => AlertRules::PM2_STATUS, 'target' => "{$name}#1@deploy", 'message' => "PM2 {$name} (id 1, deploy) en estado stopped"]);

    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe('playinn')
        ->and($posts[0]['content'])->not->toContain('[Reautenticar](')->not->toContain("\n#")->toContain('\[Reautenticar\]');
});

it('still posts the notice when Reverb is down', function () {
    $reverb = new class extends Broadcaster
    {
        public int $attempts = 0;

        public function auth($request) {}

        public function validAuthenticationResponse($request, $result) {}

        public function broadcast(array $channels, $event, array $payload = []): void
        {
            $this->attempts++;
            throw new BroadcastException('Reverb no responde');
        }
    };
    Broadcast::extend('down', fn () => $reverb);
    config(['broadcasting.default' => 'down', 'broadcasting.connections.down' => ['driver' => 'down']]);

    ($this->apply)(AlertRules::serverStatus('down', 22), $this->now);

    $posts = discordPosts($this->discord);
    expect($posts)->toHaveCount(1)->and($posts[0]['channel'])->toBe('resumen')
        ->and($posts[0]['content'])->toContain('El servidor no responde')
        ->and($reverb->attempts)->toBe(1); // the browsers were tried and missed it
});

it('sends nothing at all while newDesk runs next to monitoreo (CA-13.20)', function () {
    config(['newdesk.monitoring.notify' => false, 'newdesk.monitoring.healthchecks_url' => 'https://hc-ping.test/uuid',
        'newdesk.monitoring.digest_email_to' => 'equipo@nexosmart.com.ar']);
    Mail::fake();
    Http::fake(['https://api.playinn.test/*' => Http::response(['date' => '2026-10-15', 'errors' => []]), 'https://hc-ping.test/*' => Http::response('OK')]);
    $this->playinn->forceFill(['errors_url' => 'https://api.playinn.test/api/errors', 'errors_token' => 'token'])->save();
    app(PollerSignals::class)->markTick(now()->getTimestamp());

    ($this->apply)(AlertRules::serverStatus('down', 22), $this->now);
    noticeOf($this, ['id' => 1, 'rule' => AlertRules::SITE_DOWN, 'target' => 'playinn.app', 'message' => 'El sitio responde HTTP 502']);
    event(new VulnerabilitiesScanned($this->companyId, $this->server->id, 'Servidor 5', ['scanned_at' => '2026-10-14T08:05:00Z', 'items' => []],
        ['scanned_at' => '2026-10-15T08:05:00Z', 'items' => [['path' => '/var/www/playinn', 'manager' => 'npm', 'findings' => [['package' => 'axios', 'severity' => 'high', 'title' => 'SSRF']]]]]));
    app(Notifier::class)->summary('📋 **Resumen**');
    app(WatchProjectErrors::class)();
    app(SendAlertDigest::class)->send($this->now->subHours(9), $this->now->addMinute());
    app(SendHeartbeat::class)();

    Http::assertNothingSent();
    Mail::assertNothingOutgoing();
    expect($this->discord->posts)->toBe([]);
});
