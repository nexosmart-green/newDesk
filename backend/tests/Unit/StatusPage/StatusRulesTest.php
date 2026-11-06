<?php

use App\Domain\StatusPage\Models\Component;
use App\Domain\StatusPage\Queries\StatusPage;
use App\Domain\StatusPage\Status\ComponentState;
use App\Domain\StatusPage\Status\Evaluate;
use App\Domain\StatusPage\Status\ProbeResult;
use App\Domain\StatusPage\Status\Result;
use Carbon\CarbonImmutable;
use Tests\Support\StatusPageFixtures;

// portal/status_test.go TestEvaluateHTTP
it('judges http and health probes (D-039, D-075)', function (string $kind, ProbeResult $probe, string $status, string $phrase) {
    $result = Evaluate::http($kind, $probe, Evaluate::DEFAULT_SLOW_MS);

    expect([$result->status, $result->phrase])->toBe([$status, $phrase]);
})->with([
    'no answer' => [Component::HTTP, new ProbeResult(error: 'timeout'), 'down', 'No responde'],
    '5xx' => [Component::HTTP, new ProbeResult(503, 80), 'down', 'No responde'],
    '4xx' => [Component::HTTP, new ProbeResult(404, 80), 'degraded', 'Responde con errores'],
    '429 only limits the check' => [Component::HEALTH, new ProbeResult(429, 80), 'operational', 'Funciona normalmente'],
    'slow' => [Component::HTTP, new ProbeResult(200, 4500), 'degraded', 'Responde lento'],
    'redirect is up' => [Component::HTTP, new ProbeResult(301, 80), 'operational', 'Funciona normalmente'],
    'health ok json' => [Component::HEALTH, new ProbeResult(200, 80, body: '{"status":"ok","db":"ok"}'), 'operational', 'Funciona normalmente'],
    'health plain text' => [Component::HEALTH, new ProbeResult(200, 80, body: 'OK'), 'operational', 'Funciona normalmente'],
    'health says down' => [Component::HEALTH, new ProbeResult(200, 80, body: '{"status":"degraded"}'), 'degraded', 'Funciona con problemas'],
    'a site body is not judged' => [Component::HTTP, new ProbeResult(200, 80, body: '{"status":"down"}'), 'operational', 'Funciona normalmente'],
]);

// TestEvaluateProcesses
it('counts pm2 instances and supervisor processes', function () {
    $pm2 = ['daemons' => [['processes' => [
        ['name' => 'api', 'status' => 'online'], ['name' => 'api', 'status' => 'errored'], ['name' => 'web', 'status' => 'online'],
    ]]]];
    expect(Evaluate::pm2('api', '', $pm2))->status->toBe('degraded')->detail->toBe('1 de 2 instancias online')
        ->and(Evaluate::pm2('web', '', $pm2)->status)->toBe('operational')
        ->and(Evaluate::pm2('gone', '', $pm2)->status)->toBe('down');

    $supervisor = ['programs' => [
        ['name' => 'queue:queue_00', 'group' => 'queue', 'state' => 'RUNNING'], ['name' => 'queue:queue_01', 'group' => 'queue', 'state' => 'RUNNING'],
        ['name' => 'scheduler', 'state' => 'FATAL'],
    ]];
    expect(Evaluate::supervisor('queue', $supervisor))->status->toBe('operational')->detail->toBe('2 de 2 procesos corriendo')
        ->and(Evaluate::supervisor('scheduler', $supervisor)->status)->toBe('down');
});

// TestEvaluatePM2ErroredDaemonIsUnknown (D-088)
it('leaves unknown an app whose live daemon failed to list', function () {
    $pm2 = ['daemons' => [
        ['user' => 'alice', 'alive' => true, 'processes' => [['name' => 'web', 'status' => 'online']]],
        ['user' => 'bob', 'alive' => true, 'error' => 'pm2 bob: timed out'],
        ['user' => 'carol', 'error' => 'daemon no está corriendo'],
    ]];

    expect(Evaluate::pm2('api', 'bob', $pm2))->status->toBe('unknown')->phrase->toBe('Sin datos recientes')
        ->and(Evaluate::pm2('web', 'alice', $pm2)->status)->toBe('operational')
        ->and(Evaluate::pm2('api', 'carol', $pm2)->status)->toBe('down') // a dead daemon runs nothing
        ->and(Evaluate::pm2('api', '', $pm2)->status)->toBe('down'); // without an owner, another user's failure hides nothing
});

// TestEvaluatePM2MatchesOwner (D-089)
it('counts only the owner daemon of a pm2 component', function () {
    $pm2 = ['daemons' => [
        ['user' => 'clientb', 'processes' => [['name' => 'api-b', 'status' => 'stopped']]],
        ['user' => 'eve', 'processes' => [['name' => 'api-b', 'status' => 'online']]],
    ]];
    expect(Evaluate::pm2('api-b', 'clientb', $pm2))->status->toBe('down')->detail->toBe('0 de 1 instancias online')
        ->and(Evaluate::pm2('api-b', 'eve', $pm2)->status)->toBe('operational')
        ->and(Evaluate::pm2('api-b', '', $pm2))->status->toBe('unknown')
        ->detail->toBe('hay apps con este nombre en varios usuarios: elegí el usuario');

    $pm2['daemons'][] = ['user' => 'zed', 'alive' => true, 'error' => 'timed out'];
    expect(Evaluate::pm2('gone', 'clientb', $pm2)->status)->toBe('down')
        ->and(Evaluate::pm2('gone', 'zed', $pm2)->status)->toBe('unknown');
});

// TestEvaluateTimerIsNeverDown
it('never puts a backup timer down', function () {
    $now = CarbonImmutable::parse('2026-10-07 12:00:00', 'UTC');
    $cron = ['timers' => [
        ['unit' => 'backup-ok.timer', 'last' => $now->subHours(3)->toIso8601ZuluString(), 'result' => 'success'],
        ['unit' => 'backup-late.timer', 'last' => $now->subHours(40)->toIso8601ZuluString(), 'result' => 'success'],
        ['unit' => 'backup-failed.timer', 'last' => $now->subHour()->toIso8601ZuluString(), 'result' => 'exit-code', 'exit_status' => 1],
    ]];

    $ok = Evaluate::timer('backup-ok', $cron, Evaluate::DEFAULT_MAX_AGE_HOURS, $now);
    expect($ok->status)->toBe('operational')->and($ok->lastOkAt?->equalTo($now->subHours(3)))->toBeTrue();
    foreach (['backup-late.timer', 'backup-failed.timer', 'missing.timer'] as $unit) {
        expect(Evaluate::timer($unit, $cron, Evaluate::DEFAULT_MAX_AGE_HOURS, $now)->status)->toBe('degraded');
    }
});

// TestNextDownAfterTenMinutesDatesThePeriodFromTheFirst (D-071, CA-13.12)
it('goes down after 10 minutes of failures, dating the period from the first one (CA-13.12)', function () {
    $t0 = CarbonImmutable::parse('2026-10-07 14:02:00', 'UTC');
    $up = new Result('operational', 'Funciona normalmente');
    $down = new Result('down', 'No responde', 'timeout');

    [$state, $change] = (new ComponentState)->next($up, $t0);
    expect($state->status)->toBe('operational')->and($change['open'])->toBe('');
    [$state, $change] = $state->next($down, $t0->addMinute());
    expect($state->status)->toBe('operational')->and($change)->toBeNull()->and($state->badStreak)->toBe(1);
    for ($minute = 2; $minute <= 10; $minute++) {
        [$state, $change] = $state->next($down, $t0->addMinutes($minute));
        expect($state->status)->toBe('operational')->and($change)->toBeNull()->and($state->phrase)->toBe('Funciona normalmente');
    }
    [$state, $change] = $state->next($down, $t0->addMinutes(11));
    expect($state->status)->toBe('down')->and($change['open'])->toBe('down')
        ->and($change['started_at']->equalTo($t0->addMinute()))->toBeTrue()
        ->and($state->phrase)->toBe('No responde')
        ->and($state->statusSince->equalTo($t0->addMinute()))->toBeTrue();
    [$state, $change] = $state->next($up, $t0->addMinutes(19));
    expect($state->status)->toBe('operational')->and($change['open'])->toBe('')->and($change['at']->equalTo($t0->addMinutes(19)))->toBeTrue();
});

// TestNextShortCutNeverShows (D-071, D-121)
it('leaves no trace of a cut or a slow run shorter than 10 minutes', function () {
    $t0 = CarbonImmutable::parse('2026-10-19 14:00:00', 'UTC');
    $up = new Result('operational', 'Funciona normalmente');
    $down = new Result('down', 'No responde');
    $slow = new Result('degraded', 'Responde lento');

    [$state] = (new ComponentState)->next($up, $t0);
    for ($minute = 1; $minute <= 9; $minute++) {
        [$state, $change] = $state->next($down, $t0->addMinutes($minute));
        expect($change)->toBeNull()->and($state->status)->toBe('operational');
    }
    [$state, $change] = $state->next($up, $t0->addMinutes(10));
    expect($state->status)->toBe('operational')->and($change)->toBeNull()->and($state->badSince)->toBeNull();

    for ($minute = 11; $minute <= 19; $minute++) {
        [$state, $change] = $state->next($slow, $t0->addMinutes($minute));
        expect($change)->toBeNull()->and($state->status)->toBe('operational');
    }
    [$state, $change] = $state->next($up, $t0->addMinutes(20));
    expect($change)->toBeNull()->and($state->badSince)->toBeNull();

    for ($minute = 21; $minute < 31; $minute++) {
        [$state] = $state->next($slow, $t0->addMinutes($minute));
    }
    [$state, $change] = $state->next($slow, $t0->addMinutes(31));
    expect($state->status)->toBe('degraded')->and($change['open'])->toBe('degraded')
        ->and($change['started_at']->equalTo($t0->addMinutes(21)))->toBeTrue();
});

// TestNextDegradedThenDownWaitsTenMinutesOfDown (D-075)
it('counts the 10 minutes of down from the first down result, even after a long degraded run', function () {
    $t0 = CarbonImmutable::parse('2026-10-22 14:00:00', 'UTC');
    $down = new Result('down', 'No responde', 'timeout esperando respuesta');
    $limited = new Result('degraded', 'Responde con errores', 'HTTP 404');
    $at = fn (int $minute) => $t0->addMinutes($minute);

    [$state] = (new ComponentState)->next($limited, $at(0));
    for ($minute = 1; $minute <= 30; $minute++) {
        [$state] = $state->next($limited, $at($minute));
    }
    expect($state->status)->toBe('degraded');
    [$state, $change] = $state->next($down, $at(31));
    expect($state->status)->toBe('degraded')->and($change)->toBeNull()->and($state->downSince->equalTo($at(31)))->toBeTrue();
    [$state] = $state->next($limited, $at(32));
    expect($state->downSince)->toBeNull();
    for ($minute = 33; $minute <= 42; $minute++) {
        [$state, $change] = $state->next($down, $at($minute));
        expect($state->status)->toBe('degraded')->and($change)->toBeNull();
    }
    [$state, $change] = $state->next($down, $at(43));
    expect($state->status)->toBe('down')->and($change['open'])->toBe('down');
    [$state] = $state->next(new Result('operational', 'Funciona normalmente'), $at(44));
    expect($state->downSince)->toBeNull()->and($state->badSince)->toBeNull();
});

// TestOverallPhrase
it('says the project status in the client words (D-045)', function (array $components, string $status, string $phrase) {
    expect(StatusPage::overall(array_map(StatusPageFixtures::component(...), $components)))->toBe([$status, $phrase]);
})->with([
    'nothing' => [[], 'unknown', 'Todavía no hay chequeos configurados'],
    'only a backup' => [[['label' => 'Backups', 'status' => 'down', 'phrase' => 'x']], 'unknown', 'Todavía no hay chequeos configurados'],
    'all good' => [[['label' => 'Sitio web', 'status' => 'operational', 'phrase' => 'Funciona normalmente', 'parts' => ['web']],
        ['label' => 'Backups', 'status' => 'unknown']], 'operational', 'Todo funciona normalmente'],
    'one part' => [[['label' => 'Sitio web', 'status' => 'operational', 'phrase' => 'Funciona normalmente', 'parts' => ['web']],
        ['label' => 'API', 'status' => 'degraded', 'phrase' => 'Responde lento', 'parts' => ['app']]], 'degraded', 'Degradado: App — responde lento'],
    'the API serves app and CMS' => [[['label' => 'Sitio web', 'status' => 'operational', 'phrase' => 'Funciona normalmente', 'parts' => ['web']],
        ['label' => 'App', 'status' => 'operational', 'phrase' => 'Funciona normalmente', 'parts' => ['app']],
        ['label' => 'CMS', 'status' => 'degraded', 'phrase' => 'Responde lento', 'parts' => ['cms']],
        ['label' => 'API', 'status' => 'down', 'phrase' => 'No responde', 'parts' => ['app', 'cms']]], 'down', 'Caído: problemas en App y CMS'],
    'two down' => [[['label' => 'Sitio web', 'status' => 'down', 'phrase' => 'No responde', 'parts' => ['web']],
        ['label' => 'API', 'status' => 'down', 'phrase' => 'No responde', 'parts' => ['app']],
        ['label' => 'Backups', 'status' => 'degraded', 'phrase' => 'x']], 'down', 'Caído: problemas en Web y App'],
]);
