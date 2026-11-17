<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Discord\DiscordAskers;
use App\Domain\Assistant\Discord\DiscordInteraction;
use App\Domain\Assistant\Discord\DiscordInteractionTokens;
use App\Domain\Assistant\Discord\DiscordReply;
use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\Auth;

/**
 * RF-14.08: `/newdesk pedir pedido:…`. Only a linked team user with `assistant.use` gets a turn; anyone else gets
 * a clear ephemeral answer and nothing runs (CA-14.08). The answer arrives later by editing the "pensando…" reply.
 */
final class AskAssistantFromDiscord
{
    /** A new /newdesk within this time continues the same conversation (and Claude Code session). */
    private const CONVERSATION_HOURS = 2;

    public const NOT_LINKED = 'Tu cuenta de Discord no está vinculada a newDesk. Entrá a newDesk → Asistente → Conectar Discord, copiá el código y escribí acá /newdesk vincular codigo:TU-CÓDIGO.';

    public function __construct(
        private readonly DiscordAskers $askers,
        private readonly AskAssistant $ask,
        private readonly DiscordInteractionTokens $tokens,
    ) {}

    /** @return array<string, mixed> the interaction response */
    public function __invoke(DiscordInteraction $interaction): array
    {
        $user = $this->askers->find($interaction->discordUserId);
        if ($user === null) {
            return DiscordReply::message(self::NOT_LINKED);
        }
        Auth::setUser($user); // company scopes and policies see who is asking, as in a web request
        if (! $user->hasPermission(Permission::AssistantUse)) {
            return DiscordReply::message('Tu rol no tiene habilitado el asistente de IA.');
        }
        $text = $interaction->option('pedido');
        if ($text === '') {
            return DiscordReply::message('Escribí el pedido después de /newdesk pedir.');
        }

        $conversation = AssistantConversation::query()->forCompany($user->company_id)->where('user_id', $user->id)
            ->where('channel', AssistantChannel::Discord)->where('last_message_at', '>=', now()->subHours(self::CONVERSATION_HOURS))
            ->latest('last_message_at')->first();

        try {
            // The token is kept before the turn is queued: a turn that ends at once still finds it.
            ($this->ask)($user, mb_substr($text, 0, AssistantMessage::CONTENT_MAX), AssistantChannel::Discord, $conversation,
                fn (AssistantMessage $answer) => $this->tokens->remember($answer->id, $interaction->token));
        } catch (BusinessRuleViolation $e) {
            return DiscordReply::message($e->getMessage());
        }

        return DiscordReply::thinking();
    }
}
