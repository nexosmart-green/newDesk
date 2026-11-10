<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Clients\Models\Client;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates ($client null) or edits a client (RF-02.01).
 */
final class SaveClient
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, array $data, ?Client $client = null): Client
    {
        return DB::transaction(function () use ($actor, $data, $client) {
            $before = $client?->only(Client::EDITABLE);
            $client ??= new Client(['company_id' => $actor->company_id]);
            $client->fill(Arr::only($data, Client::EDITABLE))->save();

            ($this->recordActivity)($before === null ? 'client.created' : 'client.updated', $client, $actor, $before, $client->only(Client::EDITABLE));

            return $client;
        });
    }
}
