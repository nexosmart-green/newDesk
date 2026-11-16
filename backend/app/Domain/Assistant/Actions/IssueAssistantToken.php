<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Support\AssistantToken;
use App\Domain\Identity\Models\User;

/**
 * The ephemeral token of one assistant turn: the asker's own Sanctum token (so the MCP applies their role),
 * tied to that answer, valid for minutes and revoked when the turn ends (RevokeAssistantToken).
 */
final class IssueAssistantToken
{
    public function __invoke(User $user, AssistantMessage $answer): string
    {
        $token = $user->createToken(
            AssistantToken::NAME_PREFIX.$answer->id,
            ['mcp', AssistantToken::ABILITY],
            now()->addMinutes(AssistantToken::TTL_MINUTES),
        );
        $answer->forceFill(['access_token_id' => $token->accessToken->id])->save();

        return $token->plainTextToken;
    }
}
