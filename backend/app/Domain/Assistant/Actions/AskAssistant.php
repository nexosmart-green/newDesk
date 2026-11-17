<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Enums\MessageRole;
use App\Domain\Assistant\Enums\MessageStatus;
use App\Domain\Assistant\Jobs\RunAssistantTurn;
use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Support\AssistantLimits;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RF-14.01: a person asks. Saves the question and an answer `pending`, and queues the turn; the channel shows
 * "pensando…" until the answer is done. One turn at a time per conversation. Who may ask is AssistantConversationPolicy.
 */
final class AskAssistant
{
    public function __construct(private readonly AssistantLimits $limits) {}

    public function __invoke(User $user, string $text, AssistantChannel $channel, ?AssistantConversation $conversation = null): AssistantMessage
    {
        $text = trim($text);
        $this->limits->ensureCanAsk($user);

        $answer = DB::transaction(function () use ($user, $text, $channel, $conversation) {
            $conversation = $conversation !== null
                ? AssistantConversation::query()->forCompany($user->company_id)->lockForUpdate()->findOrFail($conversation->id)
                : AssistantConversation::query()->create([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'channel' => $channel,
                    'title' => Str::limit($text, AssistantConversation::TITLE_MAX - 3),
                ]);

            $busy = $conversation->messages()->whereIn('status', [MessageStatus::Pending, MessageStatus::Running])->exists();
            if ($busy) {
                throw new BusinessRuleViolation('Todavía estoy respondiendo lo anterior. Esperá a que termine.', 'assistant_busy', 409);
            }

            $conversation->messages()->create([
                'company_id' => $user->company_id, 'role' => MessageRole::User, 'content' => $text, 'status' => MessageStatus::Done,
            ]);
            $conversation->forceFill(['last_message_at' => now()])->save();

            return $conversation->messages()->create([
                'company_id' => $user->company_id, 'role' => MessageRole::Assistant, 'status' => MessageStatus::Pending,
            ]);
        });

        RunAssistantTurn::dispatch($answer->id)->afterCommit();

        return $answer;
    }
}
