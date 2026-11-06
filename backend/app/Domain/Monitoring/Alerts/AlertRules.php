<?php

namespace App\Domain\Monitoring\Alerts;

use App\Domain\Monitoring\Agent\Section;
use Carbon\CarbonImmutable;

/**
 * The 15 alert rules of monitoreo (alerts/rules.go): agent sections, server status and the
 * panel's own site checks in, AlertResults out (RF-13.04, CA-13.08). What some rules remember
 * between polls lives in AlertState; ApplyAlertResults turns the results into alerts.
 *
 * Sections arrive decoded as associative arrays, with the agent's field names.
 */
final class AlertRules
{
    public const SERVER_DOWN = 'server_down';

    public const AGENT_DOWN = 'agent_down';

    public const CPU = 'cpu_high';

    public const MEMORY = 'memory_high';

    public const SWAP = 'swap_high';

    public const DISK = 'disk_usage';

    public const SERVICE = 'service_down';

    public const PM2_STATUS = 'pm2_process';

    public const PM2_RESTARTS = 'pm2_restarts';

    public const SUPERVISOR = 'supervisor_program';

    public const SITE_DOWN = 'site_down';

    public const SSL_EXPIRING = 'ssl_expiring';

    public const FAILED_LOGINS = 'failed_logins_spike';

    public const FINDING = 'security_finding';

    public const TIMER_FAILED = 'timer_failed';

    public const RULES = [
        self::SERVER_DOWN, self::AGENT_DOWN, self::CPU, self::MEMORY, self::SWAP, self::DISK, self::SERVICE,
        self::PM2_STATUS, self::PM2_RESTARTS, self::SUPERVISOR, self::SITE_DOWN, self::SSL_EXPIRING,
        self::FAILED_LOGINS, self::FINDING, self::TIMER_FAILED,
    ];

    public const CRITICAL = 'critical';

    public const WARNING = 'warning';

    public const CPU_THRESHOLD = 90.0;

    public const MEMORY_THRESHOLD = 90.0;

    public const SWAP_THRESHOLD = 80.0;

    public const DISK_WARNING = 85.0;

    public const DISK_CRITICAL = 95.0;

    public const SSL_WARNING_DAYS = 14;

    public const SSL_CRITICAL_DAYS = 3;

    public const FAILED_LOGINS_PER_HOUR = 200;

    /** Seconds a PM2 restart keeps pm2_restarts firing. */
    public const PM2_RESTART_WINDOW = 900;

    /**
     * Absorbs mass restarts (needrestart after unattended-upgrades restarts nginx, FPM, MySQL,
     * Supervisor… at once): a unit must stay down across two polls and this many seconds (D-024).
     */
    public const DOWN_GRACE = 90;

    private const FAILED_LOGINS_HISTORY = 3900;

    /** The sections some rule reads. */
    private const SECTIONS = [Section::METRICS, 'services', 'pm2', 'supervisor', 'cron', 'security'];

    public function __construct(private readonly AlertState $state) {}

    /** server_down / agent_down, from the status health just decided. @return list<AlertResult> */
    public static function serverStatus(string $status, int $sshPort): array
    {
        $down = $status === 'down';
        $agentDown = $status === 'agent_down';

        return [
            new AlertResult(self::SERVER_DOWN, 0, 1, [new AlertObservation(
                '', $down, self::CRITICAL, $down ? 'El servidor no responde (ni el agente ni SSH)' : '', $status,
            )]),
            new AlertResult(self::AGENT_DOWN, 0, 1, [new AlertObservation(
                '', $agentDown, self::WARNING, $agentDown ? "El agente no responde, pero el servidor sí (SSH :{$sshPort})" : '', $status,
            )]),
        ];
    }

    /**
     * The rules fed by one section; none for the sections no rule reads, or data that is not an object.
     *
     * @return list<AlertResult>
     */
    public function forSection(int $serverId, string $section, mixed $data, CarbonImmutable $now): array
    {
        if (! in_array($section, self::SECTIONS, true)) {
            return [];
        }
        $data = $data === null ? [] : json_decode(json_encode($data), true);
        if (! is_array($data)) {
            return [];
        }
        if ($section === 'cron') {
            $this->rememberTimers($serverId, $data);
        }

        return match ($section) {
            Section::METRICS => self::metrics($data),
            'services' => [$this->services($serverId, $data)],
            'pm2' => $this->pm2($serverId, $data, $now),
            'supervisor' => [self::supervisor($data)],
            'cron' => [self::timers($data)],
            'security' => [$this->failedLogins($serverId, $data, $now), self::findings($data)],
        };
    }

    /**
     * site_down and ssl_expiring from the panel's own HTTPS checks of one server. Only 5xx or no
     * answer is down: API roots answer 403/404/405 by design (D-017), and a 429 is up (D-075).
     *
     * @param  iterable<array{server_name: string, status_code: int|null, error: string|null, ssl_days_left: int|null}>  $checks
     * @return list<AlertResult>
     */
    public static function sites(iterable $checks): array
    {
        $checks = [...$checks];
        usort($checks, fn (array $a, array $b) => strcmp($a['server_name'], $b['server_name']));
        $down = [];
        $ssl = [];
        foreach ($checks as $check) {
            $name = $check['server_name'];
            $detail = match (true) {
                ($check['error'] ?? '') !== '' => $check['error'],
                $check['status_code'] !== null => 'HTTP '.$check['status_code'],
                default => 'sin respuesta',
            };
            $unreachable = $check['status_code'] === null || $check['status_code'] >= 500;
            $down[] = new AlertObservation($name, $unreachable, self::CRITICAL, "El sitio {$name} responde {$detail}", $detail);

            if ($check['ssl_days_left'] !== null) {
                $days = $check['ssl_days_left'];
                $ssl[] = new AlertObservation(
                    $name,
                    $days < self::SSL_WARNING_DAYS,
                    $days < self::SSL_CRITICAL_DAYS ? self::CRITICAL : self::WARNING,
                    "El certificado de {$name} vence en {$days} días",
                    (string) $days,
                );
            }
        }

        return [
            new AlertResult(self::SITE_DOWN, 0, 2, $down),
            new AlertResult(self::SSL_EXPIRING, 0, 1, $ssl),
        ];
    }

    /** @return list<AlertResult> */
    private static function metrics(array $metrics): array
    {
        $cpu = (float) ($metrics['cpu']['usage_percent'] ?? 0);
        $memory = (float) ($metrics['memory']['used_percent'] ?? 0);
        $swap = (float) ($metrics['swap']['used_percent'] ?? 0);
        $swapTotal = (int) ($metrics['swap']['total_bytes'] ?? 0);

        $disks = [];
        foreach ($metrics['disks'] ?? [] as $disk) {
            $mount = (string) ($disk['mount'] ?? '');
            $used = (float) ($disk['used_percent'] ?? 0);
            $disks[] = new AlertObservation(
                $mount,
                $used >= self::DISK_WARNING,
                $used >= self::DISK_CRITICAL ? self::CRITICAL : self::WARNING,
                'Disco '.$mount.' al '.self::pct($used).' %',
                self::pct($used),
            );
        }

        return [
            new AlertResult(self::CPU, 300, 2, [new AlertObservation(
                '', $cpu > self::CPU_THRESHOLD, self::WARNING, 'CPU al '.self::pct($cpu).' % durante más de 5 minutos', self::pct($cpu),
            )]),
            new AlertResult(self::MEMORY, 180, 2, [new AlertObservation(
                '', $memory > self::MEMORY_THRESHOLD, self::WARNING, 'RAM al '.self::pct($memory).' %', self::pct($memory),
            )]),
            new AlertResult(self::SWAP, 300, 2, [new AlertObservation(
                '', $swapTotal > 0 && $swap > self::SWAP_THRESHOLD, self::WARNING, 'Swap al '.self::pct($swap).' %', self::pct($swap),
            )]),
            new AlertResult(self::DISK, 0, 2, $disks),
        ];
    }

    /**
     * Units the agent finds unhealthy, except a job a timer started that is still running: a
     * oneshot shows "activating (start)" while it works, and a failed run is timer_failed's job
     * (D-075). Anything else stuck starting and "activating (auto-restart)" (a crash loop) alert.
     */
    private function services(int $serverId, array $services): AlertResult
    {
        $launched = $this->state->timerServices($serverId);
        $observations = [];
        foreach ($services['items'] ?? [] as $service) {
            $active = (string) ($service['active'] ?? '');
            if ($active === 'not-found') {
                continue; // not installed on this server
            }
            $name = (string) ($service['name'] ?? '');
            $subState = (string) ($service['sub_state'] ?? '');
            $state = $subState === '' ? $active : "{$active} ({$subState})";
            $running = isset($launched["{$name}.service"]) && $active === 'activating' && str_starts_with($subState, 'start');
            $observations[] = new AlertObservation(
                $name, ! ($service['healthy'] ?? false) && ! $running, self::CRITICAL, "Servicio {$name} caído: {$state}", $active,
            );
        }

        return new AlertResult(self::SERVICE, self::DOWN_GRACE, 2, $observations);
    }

    /** @return list<AlertResult> */
    private function pm2(int $serverId, array $pm2, CarbonImmutable $now): array
    {
        $previous = $this->state->pm2Restarts($serverId);
        $counters = [];
        $statuses = [];
        $restarts = [];
        foreach ($pm2['daemons'] ?? [] as $daemon) {
            $user = (string) ($daemon['user'] ?? '');
            foreach ($daemon['processes'] ?? [] as $process) {
                $id = (int) ($process['pm_id'] ?? 0);
                $name = (string) ($process['name'] ?? '');
                $status = (string) ($process['status'] ?? '');
                $total = (int) ($process['restarts'] ?? 0);
                $target = "{$name}#{$id}@{$user}";
                $statuses[] = new AlertObservation(
                    $target, $status !== 'online', self::CRITICAL, "PM2 {$name} (id {$id}, {$user}) en estado {$status}", $status,
                );

                // The first sight of a counter is not an increase; processes that vanished are forgotten.
                $counter = $previous[$target] ?? null;
                $increasedAt = $counter['increased_at'] ?? null;
                if ($counter !== null && $total > $counter['restarts']) {
                    $increasedAt = $now->getTimestamp();
                }
                $counters[$target] = ['restarts' => $total, 'increased_at' => $increasedAt];
                $recent = $increasedAt !== null && $now->getTimestamp() - $increasedAt < self::PM2_RESTART_WINDOW;
                $restarts[] = new AlertObservation(
                    $target, $recent, self::WARNING, "PM2 {$name} (id {$id}) se reinició ({$total} reinicios en total)", (string) $total,
                );
            }
        }
        $this->state->savePm2Restarts($serverId, $counters);

        return [
            new AlertResult(self::PM2_STATUS, 0, 2, $statuses),
            new AlertResult(self::PM2_RESTARTS, 0, 1, $restarts),
        ];
    }

    /**
     * Trusts the agent's verdict: FATAL/BACKOFF/EXITED are unhealthy, and so is STOPPED with
     * autostart=true; a STOPPED program with autostart=false is parked on purpose.
     */
    private static function supervisor(array $supervisor): AlertResult
    {
        $observations = [];
        foreach ($supervisor['programs'] ?? [] as $program) {
            $name = (string) ($program['name'] ?? '');
            $state = (string) ($program['state'] ?? '');
            $observations[] = new AlertObservation(
                $name, ! ($program['healthy'] ?? false), self::CRITICAL, "Supervisor: {$name} en estado {$state}", $state,
            );
        }

        return new AlertResult(self::SUPERVISOR, self::DOWN_GRACE, 2, $observations);
    }

    /** Which units the server's timers start, for the service rule. */
    private function rememberTimers(int $serverId, array $cron): void
    {
        $units = [];
        foreach ($cron['timers'] ?? [] as $timer) {
            if (($timer['service'] ?? '') !== '') {
                $units[$timer['service']] = true;
            }
        }
        $this->state->saveTimerServices($serverId, $units);
    }

    /**
     * The server's own timers (backups, syncs): a last run that did not end in "success" warns until
     * the next good run. System timers (apt, logrotate…) are left out on purpose.
     */
    private static function timers(array $cron): AlertResult
    {
        $observations = [];
        foreach ($cron['timers'] ?? [] as $timer) {
            if (! ($timer['custom'] ?? false)) {
                continue;
            }
            $unit = (string) ($timer['unit'] ?? '');
            $result = (string) ($timer['result'] ?? '');
            $exit = (int) ($timer['exit_status'] ?? 0);
            $observations[] = new AlertObservation(
                $unit, $result !== '' && $result !== 'success', self::WARNING,
                "El timer {$unit} falló en su última ejecución ({$result}, código {$exit})", $result,
            );
        }

        return new AlertResult(self::TIMER_FAILED, 0, 1, $observations);
    }

    /**
     * Attempts in the last hour, from the growth of the agent's rolling 24 h counter across polls.
     * When the agent sends verified counts only those count: a local user can forge the rest
     * (D-095). Samples on another basis are not compared, so a source switch is not a spike.
     */
    private function failedLogins(int $serverId, array $security, CarbonImmutable $now): AlertResult
    {
        $logins = $security['failed_logins'] ?? [];
        [$total, $basis] = isset($logins['verified_24h']) && is_array($logins['verified_24h'])
            ? [self::authTotal($logins['verified_24h']), 'verified:'.($logins['source'] ?? '')]
            : [self::authTotal($logins['window_24h'] ?? []), '']; // older agents: every event

        $at = $now->getTimestamp();
        $kept = array_values(array_filter(
            [...$this->state->loginHistory($serverId), ['at' => $at, 'total' => $total, 'basis' => $basis]],
            fn (array $sample) => $at - $sample['at'] <= self::FAILED_LOGINS_HISTORY && $sample['basis'] === $basis,
        ));
        $this->state->saveLoginHistory($serverId, $kept);

        $lastHour = 0;
        if (count($kept) >= 2 && $at - $kept[0]['at'] >= 50 * 60) {
            $lastHour = max(0, $kept[count($kept) - 1]['total'] - $kept[0]['total']);
        }

        return new AlertResult(self::FAILED_LOGINS, 0, 1, [new AlertObservation(
            '', $lastHour > self::FAILED_LOGINS_PER_HOUR, self::WARNING,
            "{$lastHour} intentos de ingreso fallidos en la última hora", (string) $lastHour,
        )]);
    }

    private static function findings(array $security): AlertResult
    {
        $observations = [];
        foreach ($security['findings'] ?? [] as $finding) {
            if (($finding['severity'] ?? '') !== self::CRITICAL) {
                continue;
            }
            $observations[] = new AlertObservation(
                (string) ($finding['id'] ?? ''), true, self::CRITICAL, 'Hallazgo de seguridad: '.($finding['title'] ?? ''), self::CRITICAL,
            );
        }

        return new AlertResult(self::FINDING, 0, 1, $observations);
    }

    /** Failed attempts only (accepted logins excluded), as contract.AuthCounts.Total. */
    private static function authTotal(array $counts): int
    {
        $total = 0;
        foreach (['invalid_user', 'failed_password', 'preauth_closed', 'banner_exchange', 'phpmyadmin_denied', 'max_auth_attempts'] as $kind) {
            $total += (int) ($counts[$kind] ?? 0);
        }

        return $total;
    }

    private static function pct(float $value): string
    {
        return number_format($value, 1, '.', '');
    }
}
