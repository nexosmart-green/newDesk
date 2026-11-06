<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * CA-02.01: a client with projects is inactivated, never deleted. Without projects it goes
 * away together with its portal users (as in monitoreo).
 */
final class DeleteClient
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(Client $client, User $actor): void
    {
        if ($client->projects()->exists()) {
            throw new BusinessRuleViolation(
                'Un cliente con proyectos no se borra: inactivalo.',
                'client_has_projects',
            );
        }

        DB::transaction(function () use ($client, $actor) {
            ($this->recordActivity)('client.deleted', $client, $actor, $client->only(['name', 'active']));
            $client->delete();
        });
    }
}
