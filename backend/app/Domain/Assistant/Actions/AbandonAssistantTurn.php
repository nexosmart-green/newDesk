<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\MessageStatus;
use App\Domain\Assistant\Events\AssistantTurnFinished;
use App\Domain\Assistant\Models\AssistantMessage;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Closes a turn that could not finish: the answer ends `failed` with a message for the person, keeps what it
 * cost, and its ephemeral token dies. Used when the engine fails, when the worker kills the job and by the
 * scheduled sweep of turns left "pensando…" (assistant:housekeeping).
 */
final class AbandonAssistantTurn
{
    public const UNEXPECTED = 'No pude responder por un error inesperado. Probá de nuevo en un rato.';

    public const STUCK = 'La respuesta se cortó antes de terminar. Volvé a preguntar.';

    public function __invoke(AssistantMessage $answer, string $error, float $costUsd = 0.0): void
    {
        $closed = AssistantMessage::query()->withoutGlobalScopes()->whereKey($answer->id)
            ->whereIn('status', [MessageStatus::Pending, MessageStatus::Running])
            ->update(['status' => MessageStatus::Failed, 'error' => mb_substr($error, 0, 500), 'cost_usd' => $costUsd]);

        $this->revokeToken($answer);
        if ($closed === 1) {
            AssistantTurnFinished::dispatch($answer->id); // Discord says so instead of staying "pensando…"
        }
    }

    public function revokeToken(AssistantMessage $answer): void
    {
        $tokenId = AssistantMessage::query()->withoutGlobalScopes()->whereKey($answer->id)->value('access_token_id');
        if ($tokenId !== null) {
            PersonalAccessToken::query()->whereKey($tokenId)->delete();
        }
    }
}
