<?php

namespace App\Domain\Monitoring\Actions;

use App\Domain\Monitoring\Agent\AgentError;
use App\Domain\Monitoring\Agent\AgentTarget;
use App\Domain\Monitoring\Agent\Section;
use App\Domain\Monitoring\Contracts\AgentGateway;
use App\Domain\Monitoring\Models\Server;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * "Probar conexión" (RF-13.01): a fresh TLS handshake against the stored fingerprint, then
 * one health call. The fingerprint is checked before the token is sent (CA-13.02).
 */
final class TestServerConnection
{
    public function __construct(private readonly AgentGateway $gateway) {}

    /** @return array{ok: bool, latency_ms?: int, agent_version?: string, hostname?: string, error?: string} */
    public function __invoke(Server $server): array
    {
        $started = hrtime(true);
        try {
            $server->rememberPin($this->gateway->pin($server->ip, $server->agent_port, $server->tls_fingerprint));
            $health = $this->gateway->fetch(AgentTarget::fromServer($server), Section::HEALTH)->wait();
        } catch (AgentError $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (DecryptException) {
            return ['ok' => false, 'error' => 'no se pudo descifrar el token del agente'];
        }

        return [
            'ok' => true,
            'latency_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            'agent_version' => $health->agentVersion,
            'hostname' => is_string($health->data->hostname ?? null) ? $health->data->hostname : '',
        ];
    }
}
