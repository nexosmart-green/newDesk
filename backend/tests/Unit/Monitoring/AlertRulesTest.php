<?php

use App\Domain\Monitoring\Alerts\AlertResult;
use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Alerts\AlertState;
use Carbon\CarbonImmutable;

/**
 * Port of monitoreo alerts/rules_test.go and failedlogins_test.go. Sections are passed with the
 * agent's field names; the rules' memory is the (array) cache.
 */
function alertResult(array $results, string $rule): AlertResult
{
    foreach ($results as $result) {
        if ($result->rule === $rule) {
            return $result;
        }
    }
    throw new RuntimeException("no {$rule} result");
}

/** @return list<bool> */
function firingOf(AlertResult $result): array
{
    return array_map(fn ($observation) => $observation->firing, $result->observations);
}

beforeEach(function () {
    $this->rules = app(AlertRules::class);
    $this->now = CarbonImmutable::parse('2026-09-30 12:00:00', 'UTC');
});

it('applies the CPU, memory, swap and disk thresholds (CA-13.08)', function () {
    $results = $this->rules->forSection(1, 'metrics', [
        'cpu' => ['usage_percent' => 50], 'memory' => ['used_percent' => 95],
        'swap' => ['used_percent' => 67, 'total_bytes' => 1],
        'disks' => [['mount' => '/', 'used_percent' => 86], ['mount' => '/data', 'used_percent' => 97], ['mount' => '/boot', 'used_percent' => 10]],
    ], $this->now);

    expect(firingOf(alertResult($results, AlertRules::CPU)))->toBe([false])
        ->and(firingOf(alertResult($results, AlertRules::MEMORY)))->toBe([true])
        ->and(alertResult($results, AlertRules::MEMORY)->observations[0]->message)->toBe('RAM al 95.0 %')
        ->and(firingOf(alertResult($results, AlertRules::SWAP)))->toBe([false]);
    $disks = alertResult($results, AlertRules::DISK)->observations;
    expect(firingOf(alertResult($results, AlertRules::DISK)))->toBe([true, true, false])
        ->and($disks[0]->severity)->toBe('warning')
        ->and($disks[1]->severity)->toBe('critical')
        ->and($disks[1]->message)->toBe('Disco /data al 97.0 %')
        ->and($disks[1]->value)->toBe('97.0');

    $cpu = alertResult($this->rules->forSection(1, 'metrics', ['cpu' => ['usage_percent' => 97]], $this->now), AlertRules::CPU);
    expect($cpu->for)->toBe(300)->and($cpu->required)->toBe(2)
        ->and($cpu->observations[0]->message)->toBe('CPU al 97.0 % durante más de 5 minutos');
    // Without swap, a full swap means nothing.
    $swap = alertResult($this->rules->forSection(1, 'metrics', ['swap' => ['used_percent' => 100, 'total_bytes' => 0]], $this->now), AlertRules::SWAP);
    expect($swap->observations[0]->firing)->toBeFalse();
});

it('skips services that are not installed', function () {
    $result = alertResult($this->rules->forSection(1, 'services', ['items' => [
        ['name' => 'nginx', 'active' => 'active', 'healthy' => true],
        ['name' => 'mysql', 'active' => 'active', 'sub_state' => 'exited', 'healthy' => false],
        ['name' => 'apache2', 'active' => 'not-found'],
    ]], $this->now), AlertRules::SERVICE);

    expect(firingOf($result))->toBe([false, true])
        ->and($result->observations[1]->message)->toBe('Servicio mysql caído: active (exited)')
        ->and($result->for)->toBe(AlertRules::DOWN_GRACE)
        ->and($result->required)->toBe(2);
});

it('lets a backup a timer started run without calling it a service down (D-075)', function () {
    $this->rules->forSection(1, 'cron', ['timers' => [['unit' => 'db-backup.timer', 'service' => 'db-backup.service', 'custom' => true]]], $this->now);
    $firing = function (int $serverId, string $subState, string $active) {
        $result = alertResult($this->rules->forSection($serverId, 'services', ['items' => [
            ['name' => 'db-backup', 'active' => $active, 'sub_state' => $subState, 'custom' => true],
            ['name' => 'nginx', 'active' => 'activating', 'sub_state' => 'start'],
        ]], $this->now), AlertRules::SERVICE);

        return firingOf($result);
    };

    expect($firing(1, 'start', 'activating'))->toBe([false, true]) // nginx stuck starting still alerts
        ->and($firing(1, 'start-pre', 'activating')[0])->toBeFalse()
        ->and($firing(1, 'auto-restart', 'activating')[0])->toBeTrue() // a crash loop
        ->and($firing(1, 'failed', 'failed')[0])->toBeTrue()
        ->and($firing(2, 'start', 'activating')[0])->toBeTrue(); // server 2 has no such timer
});

it('fires pm2_restarts for 15 minutes after a counter goes up', function () {
    $pm2 = fn (int $restarts, string $status) => ['daemons' => [['user' => 'root', 'processes' => [
        ['pm_id' => 5, 'name' => 'farmaencuentro', 'status' => $status, 'restarts' => $restarts],
    ]]]];

    $first = $this->rules->forSection(1, 'pm2', $pm2(33, 'online'), $this->now);
    expect(firingOf(alertResult($first, AlertRules::PM2_RESTARTS)))->toBe([false]); // first sight is not an increase

    $second = $this->rules->forSection(1, 'pm2', $pm2(34, 'online'), $this->now->addMinute());
    $restart = alertResult($second, AlertRules::PM2_RESTARTS)->observations[0];
    expect($restart->firing)->toBeTrue()
        ->and($restart->target)->toBe('farmaencuentro#5@root')
        ->and($restart->message)->toBe('PM2 farmaencuentro (id 5) se reinició (34 reinicios en total)');

    $later = $this->rules->forSection(1, 'pm2', $pm2(34, 'errored'), $this->now->addMinutes(20));
    expect(firingOf(alertResult($later, AlertRules::PM2_RESTARTS)))->toBe([false])
        ->and(firingOf(alertResult($later, AlertRules::PM2_STATUS)))->toBe([true])
        ->and(alertResult($later, AlertRules::PM2_STATUS)->observations[0]->message)->toBe('PM2 farmaencuentro (id 5, root) en estado errored');
});

/** @return array<string, mixed> */
function securitySection(array $window24h, ?array $verified = null, string $source = 'journald', array $findings = []): array
{
    $logins = ['source' => $source, 'window_24h' => $window24h];
    if ($verified !== null) {
        $logins['verified_24h'] = $verified;
    }

    return ['failed_logins' => $logins, 'findings' => $findings];
}

function failedLogins(AlertRules $rules, array $security, CarbonImmutable $at): array
{
    $observation = alertResult($rules->forSection(1, 'security', $security, $at), AlertRules::FAILED_LOGINS)->observations[0];

    return [$observation->firing, $observation->value];
}

it('spots a spike of failed logins from the growth of the 24 h counter (CA-13.08)', function () {
    failedLogins($this->rules, securitySection(['invalid_user' => 369]), $this->now);

    expect(failedLogins($this->rules, securitySection(['invalid_user' => 390]), $this->now->addMinutes(55)))->toBe([false, '21'])
        ->and(failedLogins($this->rules, securitySection(['invalid_user' => 900]), $this->now->addMinutes(60))[0])->toBeTrue();
});

it('counts max_auth_attempts towards the spike', function () {
    failedLogins($this->rules, securitySection(['invalid_user' => 100, 'max_auth_attempts' => 0]), $this->now);

    expect(failedLogins($this->rules, securitySection(['invalid_user' => 100, 'max_auth_attempts' => 500]), $this->now->addMinutes(60)))
        ->toBe([true, '500']);
});

it('counts only the verified logins when the agent sends them (D-095)', function () {
    $security = fn (string $source, int $all, int $verified) => securitySection(
        ['invalid_user' => $verified, 'phpmyadmin_denied' => $all - $verified], ['invalid_user' => $verified], $source,
    );

    failedLogins($this->rules, $security('journald', 100, 100), $this->now);
    expect(failedLogins($this->rules, $security('journald', 5100, 120), $this->now->addMinutes(60)))->toBe([false, '20']) // forged phpMyAdmin lines
        ->and(failedLogins($this->rules, $security('journald', 5900, 920), $this->now->addMinutes(61))[0])->toBeTrue();

    app(AlertState::class)->forgetServer(1);
    failedLogins($this->rules, $security('/var/log/auth.log', 100, 0), $this->now);
    expect(failedLogins($this->rules, $security('/var/log/auth.log', 9000, 0), $this->now->addMinutes(60))[0])->toBeFalse() // auth.log cannot be verified
        // Once journald becomes readable, its 24 h count is not an hour's growth.
        ->and(failedLogins($this->rules, $security('journald', 9000, 900), $this->now->addMinutes(61))[0])->toBeFalse();

    // Agents that predate the verified counts keep counting every event.
    app(AlertState::class)->forgetServer(1);
    failedLogins($this->rules, securitySection(['phpmyadmin_denied' => 100]), $this->now);
    expect(failedLogins($this->rules, securitySection(['phpmyadmin_denied' => 900]), $this->now->addMinutes(60))[0])->toBeTrue();
});

it('alerts on critical security findings only', function () {
    $results = $this->rules->forSection(1, 'security', securitySection([], findings: [
        ['id' => 'os-eol', 'severity' => 'critical', 'title' => 'Ubuntu 24.10 sin soporte'],
        ['id' => 'ufw', 'severity' => 'medium', 'title' => 'x'],
    ]), $this->now);
    $findings = alertResult($results, AlertRules::FINDING)->observations;

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->target)->toBe('os-eol')
        ->and($findings[0]->message)->toBe('Hallazgo de seguridad: Ubuntu 24.10 sin soporte');
});

it('downs a site only on 5xx or no answer, and warns about certificates (CA-13.08, CA-13.09)', function () {
    $site = fn (string $name, ?int $status, ?string $error = null, ?int $days = null) => [
        'server_name' => $name, 'status_code' => $status, 'error' => $error, 'ssl_days_left' => $days,
    ];
    $results = AlertRules::sites([
        $site('www.reaquila.com', 502, days: 5),
        $site('api.farmaencuentro.com', null, 'timeout esperando respuesta', 2),
        $site('api2.farmaencuentro.com', 403),
        $site('vidacsa.com.ar', 429),
    ]);

    $down = alertResult($results, AlertRules::SITE_DOWN);
    // Sorted by name; a 403 or a 429 is not down (D-017, D-075).
    expect($down->required)->toBe(2)
        ->and(array_map(fn ($o) => $o->target, $down->observations))->toBe(['api.farmaencuentro.com', 'api2.farmaencuentro.com', 'vidacsa.com.ar', 'www.reaquila.com'])
        ->and(firingOf($down))->toBe([true, false, false, true])
        ->and($down->observations[0]->message)->toBe('El sitio api.farmaencuentro.com responde timeout esperando respuesta')
        ->and($down->observations[3]->message)->toBe('El sitio www.reaquila.com responde HTTP 502')
        ->and($down->observations[3]->value)->toBe('HTTP 502');

    $ssl = alertResult($results, AlertRules::SSL_EXPIRING)->observations;
    expect(firingOf(alertResult($results, AlertRules::SSL_EXPIRING)))->toBe([true, true])
        ->and($ssl[0]->severity)->toBe('critical')
        ->and($ssl[1]->severity)->toBe('warning')
        ->and($ssl[1]->message)->toBe('El certificado de www.reaquila.com vence en 5 días');

    expect(alertResult(AlertRules::sites([$site('a.com', null)]), AlertRules::SITE_DOWN)->observations[0]->message)
        ->toBe('El sitio a.com responde sin respuesta');
});

it('watches only the custom timers', function () {
    $result = alertResult($this->rules->forSection(1, 'cron', ['timers' => [
        ['unit' => 'backup-servidor.timer', 'custom' => true, 'result' => 'exit-code', 'exit_status' => 2],
        ['unit' => 'cloudflare-ips-sync.timer', 'custom' => true, 'result' => 'success'],
        ['unit' => 'never-ran.timer', 'custom' => true, 'result' => ''],
        ['unit' => 'apt-daily.timer', 'custom' => false, 'result' => 'exit-code'],
    ]], $this->now), AlertRules::TIMER_FAILED);

    expect(firingOf($result))->toBe([true, false, false])
        ->and($result->observations[0]->severity)->toBe('warning')
        ->and($result->observations[0]->message)->toBe('El timer backup-servidor.timer falló en su última ejecución (exit-code, código 2)');
});

it('trusts the agent on Supervisor programs, with the restart grace (D-024)', function () {
    $result = alertResult($this->rules->forSection(1, 'supervisor', ['programs' => [
        ['name' => 'reaquila-reverb:reaquila-reverb_00', 'state' => 'STOPPED', 'autostart' => false, 'healthy' => true],
        ['name' => 'teka-worker:teka-worker_00', 'state' => 'STOPPED', 'autostart' => true, 'healthy' => false],
        ['name' => 'playinn-scheduler', 'state' => 'RUNNING', 'healthy' => true],
        ['name' => 'broken', 'state' => 'FATAL', 'healthy' => false],
    ]], $this->now), AlertRules::SUPERVISOR);

    expect(firingOf($result))->toBe([false, true, false, true])
        ->and($result->required)->toBe(2)
        ->and($result->for)->toBe(90)
        ->and($result->observations[3]->message)->toBe('Supervisor: broken en estado FATAL');
});

it('tells server_down from agent_down', function () {
    $down = AlertRules::serverStatus('down', 22);
    $agentDown = AlertRules::serverStatus('agent_down', 1080);
    $online = AlertRules::serverStatus('online', 22);

    expect(firingOf(alertResult($down, AlertRules::SERVER_DOWN)))->toBe([true])
        ->and(alertResult($down, AlertRules::SERVER_DOWN)->observations[0]->message)->toBe('El servidor no responde (ni el agente ni SSH)')
        ->and(firingOf(alertResult($down, AlertRules::AGENT_DOWN)))->toBe([false])
        ->and(firingOf(alertResult($agentDown, AlertRules::AGENT_DOWN)))->toBe([true])
        ->and(alertResult($agentDown, AlertRules::AGENT_DOWN)->observations[0]->message)->toBe('El agente no responde, pero el servidor sí (SSH :1080)')
        ->and(alertResult($agentDown, AlertRules::AGENT_DOWN)->observations[0]->severity)->toBe('warning')
        ->and([...firingOf($online[0]), ...firingOf($online[1])])->toBe([false, false]);
});

it('ignores the sections no rule reads and data that is not an object', function () {
    expect($this->rules->forSection(1, 'packages', ['upgradable_list' => []], $this->now))->toBe([])
        ->and($this->rules->forSection(1, 'services', 'garbage', $this->now))->toBe([])
        // An empty answer resolves what the rule had open, as monitoreo's zero struct.
        ->and(alertResult($this->rules->forSection(1, 'services', null, $this->now), AlertRules::SERVICE)->observations)->toBe([]);
});
