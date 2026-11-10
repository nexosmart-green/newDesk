<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * CA-02.01: a client with projects is never deleted — it is deactivated instead. Its portal users go
 * with it (RF-13.07): the soft delete would not fire the users.client_id cascade.
 */
final class DeleteClient
{
    public function __construct(
        private readonly DeleteClientUser $deleteUser,
        private readonly RecordActivity $recordActivity,
    ) {}

    public function __invoke(User $actor, Client $client): void
    {
        DB::transaction(function () use ($actor, $client) {
            if ($client->projects()->withTrashed()->lockForUpdate()->exists()) {
                throw new BusinessRuleViolation(
                    "{$client->displayName()} tiene proyectos: no se borra, se inactiva.",
                    'client_has_projects',
                );
            }

            foreach ($client->users()->get() as $user) {
                ($this->deleteUser)($user, $actor);
            }
            ($this->recordActivity)('client.deleted', $client, $actor, $client->only(Client::EDITABLE));
            $client->delete();
        });
    }
}
