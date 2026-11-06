<?php

namespace App\Domain\StatusPage\Status;

use App\Domain\StatusPage\Models\Component;
use Carbon\CarbonImmutable;

/**
 * How each kind of component is judged (D-039, monitoreo portal/status.go). Agent snapshots come
 * as decoded JSON arrays.
 */
final class Evaluate
{
    public const DEFAULT_SLOW_MS = 3000;

    public const DEFAULT_MAX_AGE_HOURS = 26;

    /**
     * An http (site) or health (API) probe. 5xx or no answer is down; 4xx is degraded (the team
     * configured this exact URL) except 429, which only rate limited the check (D-075); slow is
     * degraded, and so is a /health JSON whose status is not ok.
     */
    public static function http(string $kind, ProbeResult $probe, int $slowMs): Result
    {
        $result = fn (string $status, string $phrase, string $detail) => new Result(
            $status, $phrase, $detail, $probe->latencyMs, $probe->statusCode, $probe->sslExpiresAt,
        );
        if ($probe->error !== '' || $probe->statusCode === null) {
            return $result(ComponentState::DOWN, Result::PHRASE_NO_ANSWER, $probe->error);
        }
        $code = $probe->statusCode;
        $latency = (int) $probe->latencyMs;

        return match (true) {
            $code >= 500 => $result(ComponentState::DOWN, Result::PHRASE_NO_ANSWER, "HTTP {$code}"),
            $code === 429 => $result(ComponentState::OPERATIONAL, Result::PHRASE_OK, 'HTTP 429: limitó el pedido del chequeo (cuenta como funcionando)'),
            $code >= 400 => $result(ComponentState::DEGRADED, 'Responde con errores', "HTTP {$code}"),
            $kind === Component::HEALTH && ! self::healthOk($probe->body) => $result(
                ComponentState::DEGRADED, 'Funciona con problemas', 'el /health no dice ok: '.trim(mb_strcut($probe->body, 0, 120)),
            ),
            $slowMs > 0 && $latency > $slowMs => $result(ComponentState::DEGRADED, 'Responde lento', "{$latency} ms (límite {$slowMs} ms)"),
            default => $result(ComponentState::OPERATIONAL, Result::PHRASE_OK, "HTTP {$code} en {$latency} ms"),
        };
    }

    /**
     * The online instances of the app named $target in the daemon of $owner (D-089): another user's
     * app with the same name neither masks nor fakes it. Without an owner every daemon counts,
     * unless several users run that name. An app missing while the owner's live daemon failed to
     * list is unknown, not down (D-088); a dead daemon runs nothing.
     *
     * @param  array<string, mixed>  $pm2
     */
    public static function pm2(string $target, string $owner, array $pm2): Result
    {
        if ($owner === '' && count(self::pm2Users($target, $pm2)) > 1) {
            return Result::unknown('hay apps con este nombre en varios usuarios: elegí el usuario');
        }
        [$running, $total, $failed] = [0, 0, ''];
        foreach ($pm2['daemons'] ?? [] as $daemon) {
            if ($owner !== '' && ($daemon['user'] ?? '') !== $owner) {
                continue;
            }
            if ($owner !== '' && ($daemon['alive'] ?? false) && ($daemon['error'] ?? '') !== '' && $failed === '') {
                $failed = $daemon['error'];
            }
            foreach ($daemon['processes'] ?? [] as $process) {
                if (($process['name'] ?? null) === $target) {
                    $total++;
                    $running += ($process['status'] ?? '') === 'online' ? 1 : 0;
                }
            }
        }
        if ($total === 0 && $failed !== '') {
            return Result::unknown('no aparece y pm2 no pudo listar un daemon: '.$failed);
        }

        return self::count($running, $total, 'instancias online');
    }

    /**
     * The users whose daemon runs an app named $target, in order.
     *
     * @param  array<string, mixed>  $pm2
     * @return list<string>
     */
    public static function pm2Users(string $target, array $pm2): array
    {
        $users = [];
        foreach ($pm2['daemons'] ?? [] as $daemon) {
            $runs = array_filter($daemon['processes'] ?? [], fn (array $process) => ($process['name'] ?? null) === $target);
            if ($runs !== [] && ! in_array($daemon['user'] ?? '', $users, true)) {
                $users[] = $daemon['user'] ?? '';
            }
        }

        return $users;
    }

    /**
     * The RUNNING processes of a program or group: $target matches the name, the group or the
     * "group:" prefix.
     *
     * @param  array<string, mixed>  $supervisor
     */
    public static function supervisor(string $target, array $supervisor): Result
    {
        [$running, $total] = [0, 0];
        foreach ($supervisor['programs'] ?? [] as $program) {
            $name = (string) ($program['name'] ?? '');
            if ($name !== $target && ($program['group'] ?? '') !== $target && ! str_starts_with($name, $target.':')) {
                continue;
            }
            $total++;
            $running += ($program['state'] ?? '') === 'RUNNING' ? 1 : 0;
        }

        return self::count($running, $total, 'procesos corriendo');
    }

    /**
     * A backup timer: a failed or late run is degraded, never down (the site can be up while a
     * backup failed).
     *
     * @param  array<string, mixed>  $cron
     */
    public static function timer(string $target, array $cron, int $maxAgeHours, CarbonImmutable $now): Result
    {
        $timer = null;
        foreach ($cron['timers'] ?? [] as $candidate) {
            if (in_array($target, [$candidate['unit'] ?? null, $candidate['service'] ?? null], true) || ($candidate['unit'] ?? null) === $target.'.timer') {
                $timer = $candidate;
                break;
            }
        }
        if ($timer === null) {
            return new Result(ComponentState::DEGRADED, 'Sin información del backup', 'el timer no aparece en el server');
        }
        if (($timer['last'] ?? null) === null) {
            return new Result(ComponentState::DEGRADED, 'El backup todavía no corrió', 'sin última ejecución');
        }
        $outcome = (string) ($timer['result'] ?? '');
        if ($outcome !== '' && $outcome !== 'success') {
            return new Result(ComponentState::DEGRADED, 'El último backup falló', sprintf('result=%s (exit %d)', $outcome, $timer['exit_status'] ?? 0));
        }
        $last = CarbonImmutable::parse($timer['last'])->utc();
        $age = $now->getTimestamp() - $last->getTimestamp();
        if ($age > $maxAgeHours * 3600) {
            return new Result(ComponentState::DEGRADED, 'El backup está atrasado',
                sprintf('último hace %.0f h (máximo %d h)', $age / 3600, $maxAgeHours), lastOkAt: $last);
        }

        return new Result(ComponentState::OPERATIONAL, Result::PHRASE_OK, 'último OK '.$last->toIso8601ZuluString(), lastOkAt: $last);
    }

    /**
     * Any body is fine unless it is JSON with a status that is not ok/up/healthy/pass/success:
     * plain "OK" bodies and JSON without status pass.
     */
    private static function healthOk(string $body): bool
    {
        $status = json_decode($body, true)['status'] ?? null;
        if (! is_string($status)) {
            return true;
        }

        return in_array(strtolower(trim($status)), ['ok', 'up', 'healthy', 'pass', 'success'], true);
    }

    /** "Running out of total": all is operational, some is degraded, none is down. */
    private static function count(int $running, int $total, string $noun): Result
    {
        $detail = "{$running} de {$total} {$noun}";

        return match (true) {
            $total === 0 => new Result(ComponentState::DOWN, 'Detenido', 'no aparece en el server'),
            $running === $total => new Result(ComponentState::OPERATIONAL, Result::PHRASE_OK, $detail),
            $running === 0 => new Result(ComponentState::DOWN, 'Detenido', $detail),
            default => new Result(ComponentState::DEGRADED, 'Funciona con capacidad reducida', $detail),
        };
    }
}
