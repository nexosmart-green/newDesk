<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

/**
 * A personal token to connect Claude Code by MCP (RF-12.02, monitoreo D-050): only its hash is stored and
 * the plain text is shown once. It can do what its owner's role can do at the moment of each call.
 */
final class CreatePersonalToken
{
    public const EXPIRY_DAYS = [30, 90, 365];

    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $user, string $name, ?int $expiresInDays): NewAccessToken
    {
        return DB::transaction(function () use ($user, $name, $expiresInDays) {
            $expiresAt = $expiresInDays === null ? null : CarbonImmutable::now()->addDays($expiresInDays);
            $token = $user->createToken($name, ['*'], $expiresAt);
            ($this->recordActivity)('token.created', $user, $user, null,
                ['token_id' => $token->accessToken->getKey(), 'name' => $name, 'expires_at' => $expiresAt?->toIso8601ZuluString()]);

            return $token;
        });
    }
}
