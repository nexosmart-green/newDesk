<?php

namespace App\Domain\Monitoring\Agent;

use App\Domain\Monitoring\Models\Server;

/**
 * Where and how to reach one agent. `pin` is the curl public-key pin derived from the
 * stored fingerprint; a request is never sent without it (CA-13.02).
 */
final readonly class AgentTarget
{
    public function __construct(
        public string $ip,
        public int $port,
        public string $token,
        public string $pin,
    ) {}

    /** Decrypts the token: throws when it cannot be opened. */
    public static function fromServer(Server $server): self
    {
        return new self($server->ip, $server->agent_port, $server->token, (string) $server->tls_spki_pin);
    }
}
