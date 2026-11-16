<?php

namespace App\Domain\Mcp\Actions;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * RF-12.02/05: one-click revoke by the owner, or by `mcp.manage_tokens` for anyone in the company.
 * The next request with that token gets 401 (CA-12.02).
 */
final class RevokeMcpToken
{
    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $actor, PersonalAccessToken $token): void
    {
        $owner = $token->tokenable;
        $allowed = $owner instanceof User && $owner->company_id === $actor->company_id
            && ($owner->is($actor) || $actor->hasPermission(Permission::McpManageTokens));

        if (! $allowed) {
            throw new BusinessRuleViolation('Ese token ya no existe: puede que ya lo hayas revocado. Recargá la lista.', 'not_found', 404);
        }

        ($this->recordActivity)('mcp.token_revoked', $owner, $actor, ['token' => $token->name]);
        $token->delete();
    }
}
