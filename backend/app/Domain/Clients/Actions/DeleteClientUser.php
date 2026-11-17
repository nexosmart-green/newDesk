<?php

namespace App\Domain\Clients\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use Illuminate\Support\Facades\DB;

/** A client's portal user goes away (monitoreo DELETE …/clients/{id}/users/{userId}). */
final class DeleteClientUser
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor) {
            ($this->recordActivity)('client.user_deleted', $user, $actor, $user->only(['client_id', 'email']));
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
