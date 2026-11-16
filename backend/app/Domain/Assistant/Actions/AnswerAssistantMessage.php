<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Contracts\AssistantEngine;
use App\Domain\Assistant\Enums\MessageRole;
use App\Domain\Assistant\Enums\MessageStatus;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Support\AssistantAnswer;
use App\Domain\Assistant\Support\AssistantEngineFailed;
use App\Domain\Assistant\Support\AssistantLimits;
use App\Domain\Assistant\Support\AssistantQuestion;
use App\Domain\Assistant\Support\AssistantSystemPrompt;

/**
 * Runs one turn (from the `assistant` queue): the engine answers through the MCP with the asker's ephemeral
 * token, which is ALWAYS revoked when the turn ends. Saves the answer, its cost and the Claude Code session
 * (`--resume` for the next turn of the conversation). A failure leaves the answer `failed` with a human message.
 */
final class AnswerAssistantMessage
{
    public function __construct(
        private readonly AssistantEngine $engine,
        private readonly IssueAssistantToken $issueToken,
        private readonly RevokeAssistantToken $revokeToken,
        private readonly AssistantSystemPrompt $systemPrompt,
        private readonly AssistantLimits $limits,
    ) {}

    public function __invoke(AssistantMessage $answer): void
    {
        if ($answer->status !== MessageStatus::Pending) {
            return; // already taken: a turn never runs twice
        }

        $conversation = $answer->conversation()->with('user.company')->firstOrFail();
        $user = $conversation->user;
        $question = $conversation->messages()->where('role', MessageRole::User)->where('id', '<', $answer->id)->latest('id')->value('content');
        $answer->forceFill(['status' => MessageStatus::Running])->save();

        try {
            $reply = $this->ask(new AssistantQuestion(
                text: (string) $question,
                systemPrompt: $this->systemPrompt->for($user),
                model: (string) $user->company->setting('assistant_model'),
                budgetUsd: $this->limits->turnBudget($user->company),
                token: ($this->issueToken)($user, $answer),
                sessionId: $conversation->claude_session_id,
            ));

            $answer->forceFill([
                'status' => MessageStatus::Done,
                'content' => $reply->text,
                'cost_usd' => $reply->costUsd,
                'duration_ms' => $reply->durationMs,
            ])->save();
            $conversation->forceFill([
                'claude_session_id' => $reply->sessionId ?? $conversation->claude_session_id,
                'last_message_at' => now(),
            ])->save();
        } catch (AssistantEngineFailed $e) {
            $answer->forceFill(['status' => MessageStatus::Failed, 'error' => $e->getMessage(), 'cost_usd' => $e->costUsd])->save();
        } finally {
            ($this->revokeToken)($answer);
        }
    }

    /** A session that no longer exists on this machine (new server, cleaned disk) must not block the conversation. */
    private function ask(AssistantQuestion $question): AssistantAnswer
    {
        try {
            return $this->engine->answer($question);
        } catch (AssistantEngineFailed $e) {
            if ($question->sessionId === null || $e->costUsd > 0) {
                throw $e;
            }

            return $this->engine->answer(new AssistantQuestion(
                $question->text, $question->systemPrompt, $question->model, $question->budgetUsd, $question->token,
            ));
        }
    }
}
