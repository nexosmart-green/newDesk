<?php

namespace App\Domain\Assistant\Support;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * The ephemeral token a turn of the assistant uses to reach the MCP as the person who asked (RF-14).
 * Recognized by its ability, never by name, and kept out of the personal token lists.
 */
final class AssistantToken
{
    public const ABILITY = 'assistant';

    public const NAME_PREFIX = 'assistant:';

    public const TTL_MINUTES = 10;

    public static function is(PersonalAccessToken $token): bool
    {
        return in_array(self::ABILITY, $token->abilities ?? [], true);
    }
}
