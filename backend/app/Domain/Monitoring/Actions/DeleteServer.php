<?php

namespace App\Domain\Monitoring\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Models\Server;
use App\Domain\Monitoring\Polling\PollerSignals;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * RF-13.01. Snapshots and metrics go with the server (foreign keys cascade).
 */
final class DeleteServer
{
    public function __construct(
        private readonly RecordActivity $recordActivity,
        private readonly PollerSignals $signals,
    ) {}

    public function __invoke(Server $server, User $actor): void
    {
        DB::transaction(function () use ($server, $actor) {
            ($this->recordActivity)('server.deleted', $server, $actor, $server->only(['name', 'ip', 'agent_port']));
            $server->delete();
        });
        $this->signals->requestReload();
    }
}
