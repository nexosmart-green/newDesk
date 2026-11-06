<?php

namespace App\Domain\Mcp\Actions;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Activity\RecordActivity;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Laravel\Sanctum\NewAccessToken;

/**
 * RF-12.02: personal token for Claude Code (or any MCP client). Stored hashed by Sanctum, prefixed `nd_`,
 * shown once. Its permissions are NOT frozen here: they are the role's, evaluated on each request.
 */
final class IssueMcpToken
{
    public const EXPIRY_OPTIONS = [30, 90, 365];

    public const ABILITY = 'mcp';

    public function __construct(private readonly RecordActivity $recordActivity) {}

    public function __invoke(User $user, string $name, ?int $expiresInDays): NewAccessToken
    {
        if (! $user->hasPermission(Permission::McpUse)) {
            throw new BusinessRuleViolation('Tu rol no tiene habilitado el uso de MCP.', 'mcp_not_allowed', 403);
        }
        if ($expiresInDays !== null && ! in_array($expiresInDays, self::EXPIRY_OPTIONS, true)) {
            throw new BusinessRuleViolation('El vencimiento puede ser 30, 90 o 365 días, o sin vencimiento.', 'invalid_expiry');
        }

        $token = $user->createToken(trim($name), [self::ABILITY], $expiresInDays !== null ? now()->addDays($expiresInDays) : null);

        ($this->recordActivity)('mcp.token_created', $user, $user, after: [
            'token' => $token->accessToken->name,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ]);

        return $token;
    }
}
