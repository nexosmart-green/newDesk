<?php

namespace App\Domain\Monitoring\Polling;

use App\Domain\Monitoring\Agent\Section;
use App\Domain\Monitoring\Models\Server;

/**
 * When to ask each agent what (monitoreo poller.Poller without the I/O). Pure: the
 * caller passes the time in unix seconds and reports results back.
 *
 * A due task is dispatched (its next run is set) and waits for a slot: one server holds at
 * most 2 of the 6 shared slots so a slow agent cannot starve the rest; health takes no slot
 * (D-106, CA-13.05), and neither does the site check, which only queues a job.
 */
final class PollSchedule
{
    /** Seconds between runs of each task (RF-13.02, monitoreo poller.Intervals). */
    public const INTERVALS = [
        Section::HEALTH => 30,
        Section::METRICS => 60,
        'services' => 60,
        'pm2' => 60,
        'supervisor' => 60,
        'security' => 300,
        'ports' => 300,
        'cron' => 600,
        'projects' => 600,
        'sites' => 600,
        Section::SYSTEM => 3600,
        'databases' => 3600,
        'dependencies' => 21600,
        'packages' => 43200,
        'large_files' => 21600,
        // security-scan.sh runs once a day; hourly is enough to see it the same morning.
        'vulnerabilities' => 3600,
        self::SITECHECK => 300,
    ];

    /** The panel's own HTTPS checks of the server's sites (RF-13.05), queued as CheckServerSites. */
    public const SITECHECK = 'sitecheck';

    /** Metrics cadence while someone has the server's detail open (CA-13.06). */
    public const LIVE_METRICS_INTERVAL = 5;

    /** A failed section retries after this, doubled per consecutive failure, capped at its interval (CA-13.04). */
    public const SECTION_RETRY_DELAY = 120;

    /** metrics_raw keeps one point per ~minute even in live mode. */
    public const RAW_METRICS_EVERY = 55;

    public const SHARED_SLOTS = 6;

    public const SLOTS_PER_SERVER = 2;

    /** Consecutive health failures before the SSH probe decides agent_down or down (CA-13.03). */
    public const FAILURES_BEFORE_DOWN = 2;

    /**
     * @var array<int, array{enabled: bool, status: string, credentials: string, next: array<string, int>,
     *     section_failures: array<string, int>, failures: int, last_raw: int|null, slots: int}>
     */
    private array $servers = [];

    /** @var array<string, true> "id|task" dispatched and not finished: waiting or running */
    private array $inflight = [];

    /** @var list<array{int, string}> dispatched tasks waiting for a slot */
    private array $waiting = [];

    /** @var array<string, true> "id|task" holding a shared slot */
    private array $holding = [];

    /** @var array<int, true> */
    private array $live = [];

    /**
     * Mirrors the servers table. A new server, or one whose address or credentials changed,
     * has every task due now and its failure counts reset.
     *
     * @param  iterable<array{id: int, enabled: bool, status: string, credentials: string}>  $servers
     */
    public function sync(iterable $servers, int $now): void
    {
        $seen = [];
        foreach ($servers as $server) {
            $id = $server['id'];
            $seen[$id] = true;
            $state = $this->servers[$id] ?? null;
            if ($state === null || $state['credentials'] !== $server['credentials']) {
                $state = [
                    'next' => array_fill_keys(array_keys(self::INTERVALS), $now),
                    'section_failures' => [],
                    'failures' => 0,
                    'last_raw' => $state['last_raw'] ?? null,
                    'slots' => $state['slots'] ?? 0,
                ];
            }
            $this->servers[$id] = [
                ...$state,
                'enabled' => $server['enabled'],
                'status' => $server['status'],
                'credentials' => $server['credentials'],
            ];
        }

        $this->servers = array_intersect_key($this->servers, $seen);
        $this->live = array_intersect_key($this->live, $seen);
    }

    /** Dispatches every due task. Sections need a reachable agent: they wait until it is online; sites do not. */
    public function collect(int $now): void
    {
        foreach ($this->servers as $id => $state) {
            if (! $state['enabled']) {
                continue;
            }
            foreach ($state['next'] as $task => $due) {
                if ($due > $now || isset($this->inflight["{$id}|{$task}"])) {
                    continue;
                }
                if (! self::isCheck($task) && $state['status'] !== Server::STATUS_ONLINE) {
                    continue;
                }
                $interval = $task === Section::METRICS && isset($this->live[$id])
                    ? self::LIVE_METRICS_INTERVAL
                    : self::INTERVALS[$task];
                $this->servers[$id]['next'][$task] = $now + $interval;
                $this->inflight["{$id}|{$task}"] = true;
                $this->waiting[] = [$id, $task];
            }
        }
    }

    /**
     * Takes the dispatched tasks that may run now: every health and site check, and sections while
     * their server and the shared pool have slots. Each one must be finish()ed.
     *
     * @return list<array{int, string}>
     */
    public function startable(): array
    {
        $start = [];
        $waiting = [];
        foreach ($this->waiting as [$id, $task]) {
            $key = "{$id}|{$task}";
            if (! isset($this->servers[$id])) {
                unset($this->inflight[$key]); // deleted while it waited

                continue;
            }
            if (self::isCheck($task)) {
                $start[] = [$id, $task];
            } elseif (count($this->holding) < self::SHARED_SLOTS && $this->servers[$id]['slots'] < self::SLOTS_PER_SERVER) {
                $this->servers[$id]['slots']++;
                $this->holding[$key] = true;
                $start[] = [$id, $task];
            } else {
                $waiting[] = [$id, $task];
            }
        }
        $this->waiting = $waiting;

        return $start;
    }

    /** Health and site checks: they take no slot and run whatever the server status. */
    private static function isCheck(string $task): bool
    {
        return $task === Section::HEALTH || $task === self::SITECHECK;
    }

    public function finish(int $serverId, string $task): void
    {
        $key = "{$serverId}|{$task}";
        unset($this->inflight[$key]);
        if (isset($this->holding[$key])) {
            unset($this->holding[$key]);
            if (isset($this->servers[$serverId])) {
                $this->servers[$serverId]['slots']--;
            }
        }
    }

    public function busy(): bool
    {
        return $this->inflight !== [];
    }

    /** @return list<int> */
    public function serverIds(): array
    {
        return array_keys($this->servers);
    }

    public function setStatus(int $serverId, string $status): void
    {
        if (isset($this->servers[$serverId])) {
            $this->servers[$serverId]['status'] = $status;
        }
    }

    /** @return int consecutive failures so far */
    public function healthFailed(int $serverId): int
    {
        if (! isset($this->servers[$serverId])) {
            return 0;
        }

        return ++$this->servers[$serverId]['failures'];
    }

    public function healthSucceeded(int $serverId): void
    {
        if (isset($this->servers[$serverId])) {
            $this->servers[$serverId]['failures'] = 0;
        }
    }

    /** Brings a failed section forward, never later than its regular run (CA-13.04). */
    public function sectionFailed(int $serverId, string $section, int $now): void
    {
        if (! isset($this->servers[$serverId])) {
            return;
        }
        $failures = ($this->servers[$serverId]['section_failures'][$section] ?? 0) + 1;
        $this->servers[$serverId]['section_failures'][$section] = $failures;
        $delay = min(self::SECTION_RETRY_DELAY << min($failures - 1, 10), self::INTERVALS[$section]);
        $this->servers[$serverId]['next'][$section] = min($this->servers[$serverId]['next'][$section], $now + $delay);
    }

    public function sectionSucceeded(int $serverId, string $section): void
    {
        unset($this->servers[$serverId]['section_failures'][$section]);
    }

    /**
     * "Forzar actualización": one section, or every task when null, due right away.
     *
     * @return bool false when the server is unknown (the caller reloads)
     */
    public function trigger(int $serverId, ?string $section, int $now): bool
    {
        if (! isset($this->servers[$serverId])) {
            return false;
        }
        foreach (array_keys($this->servers[$serverId]['next']) as $task) {
            if ($section === null || $task === $section) {
                $this->servers[$serverId]['next'][$task] = $now;
            }
        }

        return true;
    }

    /**
     * The servers someone is watching. A server that just went live gets its metrics now;
     * one that stops keeps the 5 s run already scheduled, then goes back to 1 min.
     *
     * @param  list<int>  $serverIds
     */
    public function setLive(array $serverIds, int $now): void
    {
        $live = [];
        foreach ($serverIds as $id) {
            if (! isset($this->servers[$id])) {
                continue;
            }
            $live[$id] = true;
            if (! isset($this->live[$id])) {
                $this->servers[$id]['next'][Section::METRICS] = $now;
            }
        }
        $this->live = $live;
    }

    public function isLive(int $serverId): bool
    {
        return isset($this->live[$serverId]);
    }

    /** True at most once per RAW_METRICS_EVERY of agent time; records the point as taken. */
    public function takeRawMetric(int $serverId, int $collectedAt): bool
    {
        if (! isset($this->servers[$serverId])) {
            return false;
        }
        $last = $this->servers[$serverId]['last_raw'];
        if ($last !== null && $collectedAt - $last < self::RAW_METRICS_EVERY) {
            return false;
        }
        $this->servers[$serverId]['last_raw'] = $collectedAt;

        return true;
    }
}
