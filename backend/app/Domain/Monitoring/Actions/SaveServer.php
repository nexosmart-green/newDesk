<?php

namespace App\Domain\Monitoring\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Polling\PollerSignals;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a server, or updates it when one is given (RF-13.01). Any update starts the
 * server over as "unknown" until its next health check, as in monitoreo.
 */
final class SaveServer
{
    /** What the audit trail shows: never the token (CA-13.01). */
    private const AUDITED = ['name', 'ip', 'ssh_port', 'agent_port', 'tls_fingerprint', 'enabled'];

    public function __construct(
        private readonly RecordActivity $recordActivity,
        private readonly PollerSignals $signals,
    ) {}

    /**
     * @param  array{name: string, ip: string, ssh_port?: int|null, agent_port: int, token?: string|null,
     *     tls_fingerprint?: string|null, enabled?: bool}  $input  An empty token or fingerprint keeps the stored one.
     */
    public function __invoke(array $input, User $actor, ?Server $server = null): Server
    {
        $attributes = [
            'name' => $input['name'],
            'ip' => $input['ip'],
            'ssh_port' => isset($input['ssh_port']) ? (int) $input['ssh_port'] : null,
            'agent_port' => (int) $input['agent_port'],
            'tls_fingerprint' => ($input['tls_fingerprint'] ?? null) ?: $server?->tls_fingerprint,
            'enabled' => (bool) ($input['enabled'] ?? $server->enabled ?? true),
        ];
        $token = ($input['token'] ?? null) ?: null;

        $moved = $server !== null && ($attributes['ip'] !== $server->ip
            || $attributes['agent_port'] !== $server->agent_port
            || $attributes['tls_fingerprint'] !== $server->tls_fingerprint);
        if ($moved && $token === null) {
            // CA-13.07 (monitoreo H3): the stored token only goes to the agent it was entered for.
            throw ValidationException::withMessages([
                'token' => 'Cambiaste la IP, el puerto del agente o el fingerprint: volvé a cargar el token.',
            ]);
        }

        $saved = DB::transaction(function () use ($attributes, $token, $actor, $server) {
            $before = $server?->only(self::AUDITED);
            $server ??= new Server(['company_id' => $actor->company_id]);
            $server->fill($attributes);
            if ($token !== null) {
                $server->token = $token;
            }
            $server->status = Server::STATUS_UNKNOWN;
            $server->save();

            ($this->recordActivity)(
                $before === null ? 'server.created' : 'server.updated',
                $server,
                $actor,
                $before,
                $server->only(self::AUDITED),
            );

            return $server;
        });
        $this->signals->requestReload();

        return $saved;
    }
}
