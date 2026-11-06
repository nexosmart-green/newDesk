<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

/** Revokes one of the user's own tokens (RF-12.02): it stops working on the next call. */
final class RevokePersonalToken
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $user, int $tokenId): void
    {
        $token = $user->tokens()->whereKey($tokenId)->first() ?? throw new BusinessRuleViolation('Token no encontrado.', 'not_found', 404);
        DB::transaction(function () use ($user, $token) {
            $token->delete();
            ($this->recordActivity)('token.revoked', $user, $user, ['token_id' => $token->getKey(), 'name' => $token->name]);
        });
    }
}
