<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/**
 * Creates a client, or updates it when one is given (RF-02.01). Inactivating is an
 * update (`active: false`), which is how a client with projects is retired (CA-02.01).
 */
final class SaveClient
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  array<string, mixed>  $attributes */
    public function __invoke(array $attributes, User $actor, ?Client $client = null): Client
    {
        return DB::transaction(function () use ($attributes, $actor, $client) {
            $before = $client?->only(array_keys($attributes));
            $client ??= new Client(['company_id' => $actor->company_id]);
            $client->fill($attributes)->save();

            ($this->recordActivity)(
                $before === null ? 'client.created' : 'client.updated',
                $client,
                $actor,
                $before,
                $client->only(array_keys($attributes)),
            );

            return $client;
        });
    }
}
