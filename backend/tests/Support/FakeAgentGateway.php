<?php

namespace Tests\Support;

use App\Domain\Monitoring\Agent\AgentError;
use App\Domain\Monitoring\Agent\AgentResponse;
use App\Domain\Monitoring\Agent\AgentTarget;
use App\Domain\Monitoring\Contracts\AgentGateway;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;

/**
 * An in-memory monitor-agent (monitoreo's poller_test fakeAgent): serves sections, can be
 * broken, rejects a wrong token and a wrong certificate, and counts what was asked.
 */
final class FakeAgentGateway implements AgentGateway
{
    public const TOKEN = 'fake-agent-token-0123456789';

    public const FINGERPRINT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** @var array<string, mixed> section => data */
    public array $sections = [];

    /** @var array<string, true> sections answering 503 */
    public array $failing = [];

    public bool $broken = false;

    public bool $sshUp = false;

    /** @var array<string, int> */
    public array $hits = [];

    /** @var list<string> tokens received, in order */
    public array $tokensSeen = [];

    public int $pinCalls = 0;

    public function pin(string $ip, int $port, string $fingerprint): string
    {
        $this->pinCalls++;
        if ($fingerprint !== self::FINGERPRINT) {
            throw AgentError::pinMismatch();
        }

        return self::pinFor($fingerprint);
    }

    public function fetch(AgentTarget $target, string $section): PromiseInterface
    {
        if ($target->pin !== self::pinFor(self::FINGERPRINT)) {
            return Create::rejectionFor(AgentError::pinMismatch());
        }
        $this->hits[$section] = ($this->hits[$section] ?? 0) + 1;
        $this->tokensSeen[] = $target->token;

        return match (true) {
            $target->token !== self::TOKEN => Create::rejectionFor(AgentError::unauthorized()),
            $this->broken, isset($this->failing[$section]) => Create::rejectionFor(AgentError::other('agent 503: collector failed')),
            ! array_key_exists($section, $this->sections) => Create::rejectionFor(AgentError::unsupported()),
            default => Create::promiseFor(new AgentResponse(
                now()->toImmutable()->utc(),
                'v-test',
                json_decode(json_encode($this->sections[$section])),
            )),
        };
    }

    public function probe(string $ip, int $port, int $timeoutSeconds): PromiseInterface
    {
        return Create::promiseFor($this->sshUp);
    }

    public function tick(): void
    {
        Utils::queue()->run();
    }

    public static function pinFor(string $fingerprint): string
    {
        return base64_encode(hash('sha256', $fingerprint, true));
    }

    public function hits(string $section): int
    {
        return $this->hits[$section] ?? 0;
    }

    /** monitoreo poller_test metricsWithDisk. @return array<string, mixed> */
    public static function metricsWithDisk(float $diskPercent): array
    {
        return [
            'cpu' => ['usage_percent' => 10, 'load1' => 0.3],
            'memory' => ['used_percent' => 60, 'total_bytes' => 4 << 30],
            'swap' => ['used_percent' => 20, 'total_bytes' => 4 << 30],
            'disks' => [['mount' => '/', 'used_percent' => $diskPercent]],
            'network' => [['name' => 'ens3', 'rx_bytes_per_sec' => 1000, 'tx_bytes_per_sec' => 500]],
            'uptime_seconds' => 1724400,
        ];
    }
}
