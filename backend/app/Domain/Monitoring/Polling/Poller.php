<?php

namespace App\Domain\Monitoring\Polling;

use App\Domain\Monitoring\Actions\ApplyAlertResults;
use App\Domain\Monitoring\Agent\AgentError;
use App\Domain\Monitoring\Agent\AgentResponse;
use App\Domain\Monitoring\Agent\AgentTarget;
use App\Domain\Monitoring\Agent\MetricsSummary;
use App\Domain\Monitoring\Agent\Section;
use App\Domain\Monitoring\Alerts\AlertResult;
use App\Domain\Monitoring\Alerts\AlertRules;
use App\Domain\Monitoring\Alerts\AlertState;
use App\Domain\Monitoring\Contracts\AgentGateway;
use App\Domain\Monitoring\Events\MetricsCollected;
use App\Domain\Monitoring\Events\ServerStatusChanged;
use App\Domain\Monitoring\Events\SnapshotSaved;
use App\Domain\Monitoring\Jobs\CheckServerSites;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Models\ServerSnapshot;
use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polls every agent (RF-13.02): health and each section on its interval, all at once through
 * the gateway's promises. PollSchedule decides what is due; this class does the I/O, saves what
 * comes back and applies the alert rules to it (RF-13.04). Site checks are queued (RF-13.05).
 * Driven by `monitoring:poll`; tests call tick() and drain() with a fake clock.
 */
final class Poller
{
    private const RELOAD_EVERY = 15;

    private const SSH_PROBE_TIMEOUT = 6;

    private PollSchedule $schedule;

    /** @var array<int, Server> */
    private array $servers = [];

    private bool $dirty = true;

    private ?int $lastReload = null;

    private ?int $reloadRequests = null;

    public function __construct(
        private readonly AgentGateway $gateway,
        private readonly PollerSignals $signals,
        private readonly AlertRules $rules,
        private readonly ApplyAlertResults $alerts,
        private readonly AlertState $alertState,
    ) {
        $this->schedule = new PollSchedule;
    }

    /** Ticks every second until $shouldStop says so, then lets the running calls finish. */
    public function run(callable $shouldStop): void
    {
        $nextTick = 0.0;
        while (! $shouldStop()) {
            // A database or Redis blip is logged and retried next second, as monitoreo does: crashing
            // in a loop would make supervisor give up on the process.
            try {
                if (microtime(true) >= $nextTick) {
                    $nextTick = microtime(true) + 1;
                    $this->tick();
                }
                $this->pump();
            } catch (Throwable $e) {
                report($e);
            }
            usleep(20_000);
        }
        $this->drain();
    }

    public function tick(): void
    {
        $now = now()->getTimestamp();
        $this->signals->markTick($now);
        $signals = $this->signals->take($this->schedule->serverIds());

        if ($this->dirty || $signals['reload'] !== $this->reloadRequests
            || $this->lastReload === null || $now - $this->lastReload >= self::RELOAD_EVERY) {
            $this->reloadRequests = $signals['reload'];
            $this->reload($now);
        }
        foreach ($signals['refresh'] as $serverId => $sections) {
            foreach ($sections as $section) {
                $this->dirty = ! $this->schedule->trigger($serverId, $section, $now) || $this->dirty;
            }
        }
        $this->schedule->setLive($signals['live'], $now);
        $this->schedule->collect($now);
        $this->pump();
    }

    /** Starts what the slots allow and moves running calls forward. */
    public function pump(): void
    {
        foreach ($this->schedule->startable() as [$serverId, $task]) {
            $this->start($serverId, $task);
        }
        $this->gateway->tick();
    }

    /**
     * One section of one server right away, outside the schedule (monitoreo Poller.PollNow): the
     * live check of a report refreshes the agents its components read. Saved, broadcast and
     * alerted like a regular poll; the promise settles once that is done (driven by the
     * gateway's tick()).
     */
    public function pollNow(Server $server, string $section): PromiseInterface
    {
        return $server->enabled
            ? $this->pollSection($server, $section)
            : Create::rejectionFor(AgentError::other('el server está deshabilitado'));
    }

    /** Waits for every dispatched task, including the ones still waiting for a slot. */
    public function drain(): void
    {
        while ($this->schedule->busy()) {
            $this->pump();
            usleep(1_000);
        }
    }

    private function reload(int $now): void
    {
        $servers = Server::query()->orderBy('name')->get()->keyBy('id');
        $this->schedule->sync($servers->map(fn (Server $server) => [
            'id' => $server->id,
            'enabled' => $server->enabled,
            'status' => $server->status,
            // A new address, fingerprint or token starts the server over.
            'credentials' => implode('|', [$server->ip, $server->agent_port, $server->tls_fingerprint, $server->getRawOriginal('token')]),
        ])->values()->all(), $now);
        foreach (array_keys(array_diff_key($this->servers, $servers->all())) as $deletedId) {
            $this->alertState->forgetServer($deletedId);
        }
        $this->servers = $servers->all();
        $this->dirty = false;
        $this->lastReload = $now;
    }

    private function start(int $serverId, string $task): void
    {
        $server = $this->servers[$serverId] ?? null;
        try {
            $promise = match (true) {
                $server === null => Create::promiseFor(null),
                $task === Section::HEALTH => $this->checkHealth($server),
                $task === PollSchedule::SITECHECK => $this->queueSiteCheck($server),
                default => $this->pollSection($server, $task),
            };
        } catch (Throwable $e) {
            $promise = Create::rejectionFor($e);
        }

        $promise
            ->then(null, function (Throwable $e) use ($serverId, $task) {
                Log::error('monitoring poller: task failed', ['server_id' => $serverId, 'task' => $task, 'error' => $e->getMessage()]);
                report($e);
            })
            ->then(fn () => $this->schedule->finish($serverId, $task));
    }

    /** ShouldBeUnique: while a round of this server is queued or running, this is a no-op. */
    private function queueSiteCheck(Server $server): PromiseInterface
    {
        CheckServerSites::dispatch($server->id);

        return Create::promiseFor(null);
    }

    /**
     * Online, agent_down or down (CA-13.03). One failure is tolerated; from the second on,
     * a TCP connect to the SSH port tells a dead agent from a dead server.
     */
    private function checkHealth(Server $server): PromiseInterface
    {
        try {
            $request = $this->gateway->fetch($this->target($server), Section::HEALTH);
        } catch (AgentError $e) {
            $request = Create::rejectionFor($e);
        }

        return $request->then(
            function (AgentResponse $response) use ($server) {
                $this->schedule->healthSucceeded($server->id);
                $this->saveStatus($server->id, Server::STATUS_ONLINE, $response->agentVersion);
            },
            function (Throwable $e) use ($server) {
                if (! $e instanceof AgentError) {
                    throw $e;
                }
                if ($this->schedule->healthFailed($server->id) < PollSchedule::FAILURES_BEFORE_DOWN) {
                    Log::warning('monitoring poller: health failed once', ['server' => $server->name, 'error' => $e->getMessage()]);

                    return null;
                }

                return $this->gateway->probe($server->ip, $server->sshPortOrDefault(), self::SSH_PROBE_TIMEOUT)
                    ->then(fn (bool $sshUp) => $this->saveStatus($server->id, $sshUp ? Server::STATUS_AGENT_DOWN : Server::STATUS_DOWN));
            },
        );
    }

    /**
     * The token decrypted, plus the TLS pin, derived on the first contact from the stored
     * fingerprint (ADR-0002 §2). Throws AgentError when neither can be had.
     */
    private function target(Server $server): AgentTarget
    {
        if ($server->tls_spki_pin === null) {
            // ponytail: blocking handshake (≤5 s) once per server and address; a host that drops packets and has no pin yet stalls the loop that long each health round. Make it async if that ever matters.
            $server->rememberPin($this->gateway->pin($server->ip, $server->agent_port, $server->tls_fingerprint));
        }
        try {
            return AgentTarget::fromServer($server);
        } catch (DecryptException) {
            throw AgentError::other('no se pudo descifrar el token del agente');
        }
    }

    private function saveStatus(int $serverId, string $status, string $agentVersion = ''): void
    {
        $server = $this->servers[$serverId] ?? null;
        if ($server === null) {
            return; // deleted meanwhile
        }
        $previous = $server->status;
        $changes = ['status' => $status];
        if ($status === Server::STATUS_ONLINE) {
            $changes['last_seen_at'] = now()->utc();
        }
        if ($agentVersion !== '') {
            $changes['agent_version'] = $agentVersion;
        }
        Server::withoutTimestamps(fn () => $server->forceFill($changes)->saveQuietly());
        $this->schedule->setStatus($serverId, $status);

        if ($status !== $previous) {
            Log::info('monitoring poller: status changed', ['server' => $server->name, 'from' => $previous, 'to' => $status]);
            $this->broadcast(new ServerStatusChanged($server->company_id, $serverId, $status, $server->last_seen_at?->toIso8601ZuluString()));
        }
        $this->applyAlerts($server, fn () => AlertRules::serverStatus($status, $server->sshPortOrDefault()));
    }

    private function pollSection(Server $server, string $section): PromiseInterface
    {
        try {
            $request = $this->gateway->fetch($this->target($server), $section);
        } catch (AgentError $e) {
            $request = Create::rejectionFor($e);
        }

        return $request->then(
            fn (AgentResponse $response) => $this->saveSection($server, $section, $response),
            function (Throwable $e) use ($server, $section) {
                if (! $e instanceof AgentError) {
                    throw $e;
                }
                Log::warning('monitoring poller: section failed', ['server' => $server->name, 'section' => $section, 'error' => $e->getMessage()]);
                // The last good data stays; only the error and the fetch time change.
                ServerSnapshot::query()->upsert([[
                    'server_id' => $server->id, 'section' => $section, 'company_id' => $server->company_id,
                    'fetched_at' => now()->utc(), 'error' => $e->getMessage(),
                ]], ['server_id', 'section'], ['fetched_at', 'error']);
                // An old agent answers 404 until it is updated: retrying sooner only adds noise.
                if ($e->kind !== AgentError::UNSUPPORTED) {
                    $this->schedule->sectionFailed($server->id, $section, now()->getTimestamp());
                }
            },
        );
    }

    private function saveSection(Server $server, string $section, AgentResponse $response): void
    {
        $collectedAt = $response->collectedAt ?? CarbonImmutable::now()->utc();
        ServerSnapshot::query()->upsert([[
            'server_id' => $server->id,
            'section' => $section,
            'company_id' => $server->company_id,
            'collected_at' => $collectedAt,
            'fetched_at' => now()->utc(),
            'agent_version' => $response->agentVersion,
            'data' => $response->dataJson(),
            'warnings' => $response->warnings === [] ? null : json_encode($response->warnings, JSON_UNESCAPED_UNICODE),
            'error' => null,
        ]], ['server_id', 'section'], ['collected_at', 'fetched_at', 'agent_version', 'data', 'warnings', 'error']);
        $this->schedule->sectionSucceeded($server->id, $section);
        $this->broadcast(new SnapshotSaved($server->company_id, $server->id, $section, $collectedAt->toIso8601ZuluString()));

        if ($section === Section::METRICS) {
            $this->afterMetrics($server, $response->data, $collectedAt);
        } elseif ($section === Section::SYSTEM) {
            $this->saveIdentity($server, $response->data);
        }
        $this->applyAlerts($server, fn () => $this->rules->forSection($server->id, $section, $response->data, CarbonImmutable::now()));
    }

    /**
     * An alert failure is logged and the polling goes on, as in monitoreo.
     *
     * @param  callable(): list<AlertResult>  $results
     */
    private function applyAlerts(Server $server, callable $results): void
    {
        try {
            ($this->alerts)($server, $results());
        } catch (Throwable $e) {
            Log::error('monitoring poller: alerts failed', ['server' => $server->name, 'error' => $e->getMessage()]);
            report($e);
        }
    }

    private function afterMetrics(Server $server, mixed $metrics, CarbonImmutable $collectedAt): void
    {
        $summary = MetricsSummary::from($metrics, $collectedAt);
        $live = $this->schedule->isLive($server->id);
        $this->broadcast(new MetricsCollected($server->company_id, $server->id, $summary, $live ? $metrics : null));

        if (! $this->schedule->takeRawMetric($server->id, $collectedAt->getTimestamp())) {
            return;
        }
        $rx = $tx = 0.0;
        foreach ((array) ($metrics->network ?? []) as $interface) {
            $rx += (float) ($interface->rx_bytes_per_sec ?? 0);
            $tx += (float) ($interface->tx_bytes_per_sec ?? 0);
        }
        DB::table('monitoring_metrics_raw')->insert([
            'company_id' => $server->company_id,
            'server_id' => $server->id,
            'ts' => $collectedAt,
            'cpu' => $summary['cpu_percent'],
            'memory' => $summary['memory_percent'],
            'swap' => $summary['swap_percent'],
            'disk' => $summary['disk_percent'],
            'load1' => $summary['load1'],
            'rx_bps' => $rx,
            'tx_bps' => $tx,
        ]);
    }

    /** monitoreo SetServerIdentity: the SSH port is only filled when nobody typed one in. */
    private function saveIdentity(Server $server, mixed $system): void
    {
        $sshPort = (int) ($system->ssh_port ?? 0);
        Server::query()->whereKey($server->id)->toBase()->update([
            'hostname' => ($system->hostname ?? '') ?: null,
            'os' => ($system->os->pretty_name ?? '') ?: null,
            'ssh_port' => DB::raw($sshPort > 0 ? "COALESCE(ssh_port, {$sshPort})" : 'ssh_port'),
        ]);
        $this->dirty = true;
    }

    /** Realtime is best effort: Reverb being down must not stop the polling. */
    private function broadcast(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            Log::warning('monitoring poller: broadcast failed', ['event' => $event::class, 'error' => $e->getMessage()]);
        }
    }
}
