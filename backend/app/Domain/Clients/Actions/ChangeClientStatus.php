<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;

final class ChangeClientStatus
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, Client $client, ClientStatus $status): Client
    {
        if ($client->status === $status) {
            return $client;
        }

        $before = $client->status->value;
        $client->forceFill(['status' => $status])->save();
        ($this->recordActivity)('client.status_changed', $client, $actor, ['status' => $before], ['status' => $status->value]);

        return $client;
    }
}
