<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Discord\DiscordAnswerMessage;
use App\Domain\Assistant\Discord\DiscordAskers;
use App\Domain\Assistant\Discord\DiscordInteraction;
use App\Domain\Assistant\Discord\DiscordReply;
use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * RF-14.08: the Confirmar / Cancelar buttons under a Discord answer. Only the person who asked resolves them
 * (the same policy as the web, CA-14.08); the message is then redrawn with how it ended.
 */
final class ResolveProposalFromDiscord
{
    public const NOT_YOURS = 'Solo quien hizo el pedido puede confirmarlo o cancelarlo.';

    public function __construct(
        private readonly DiscordAskers $askers,
        private readonly ConfirmAssistantProposal $confirm,
        private readonly CancelAssistantProposal $cancel,
    ) {}

    /** @return array<string, mixed> the interaction response */
    public function __invoke(DiscordInteraction $interaction): array
    {
        $button = DiscordAnswerMessage::parseCustomId($interaction->customId);
        $user = $this->askers->find($interaction->discordUserId);
        if ($button === null || $user === null) {
            return DiscordReply::message(self::NOT_YOURS);
        }
        Auth::setUser($user);

        $proposal = AssistantProposal::query()->forCompany($user->company_id)->find($button['proposal']);
        if ($proposal === null || ! Gate::forUser($user)->inspect('resolve', $proposal)->allowed()) {
            return DiscordReply::message(self::NOT_YOURS);
        }

        try {
            $button['action'] === DiscordAnswerMessage::CONFIRM
                ? ($this->confirm)($user, $proposal, AssistantChannel::Discord)
                : ($this->cancel)($proposal, AssistantChannel::Discord);
        } catch (BusinessRuleViolation $e) {
            return DiscordReply::message($e->getMessage());
        }

        $answer = AssistantMessage::query()->with('proposals')->findOrFail($proposal->message_id);
        $message = DiscordAnswerMessage::of($answer);

        return DiscordReply::update($message['content'], $message['components']);
    }
}
