<?php

namespace App\Http\Controllers\Api;

use App\Domain\Assistant\Actions\AskAssistantFromDiscord;
use App\Domain\Assistant\Actions\LinkDiscordAccount;
use App\Domain\Assistant\Actions\ResolveProposalFromDiscord;
use App\Domain\Assistant\Discord\DiscordInteraction;
use App\Domain\Assistant\Discord\DiscordReply;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RF-14.08: Discord's interactions endpoint for /newdesk (signature already verified by VerifyDiscordSignature).
 * Answers within Discord's 3 seconds: a turn of the assistant is queued and answered later.
 */
class DiscordInteractionController extends Controller
{
    public const HELP = 'Usá /newdesk pedir pedido:… para hablar con el asistente, o /newdesk vincular codigo:… para conectar tu cuenta.';

    public function __invoke(
        Request $request,
        AskAssistantFromDiscord $ask,
        LinkDiscordAccount $link,
        ResolveProposalFromDiscord $resolve,
    ): JsonResponse {
        $interaction = DiscordInteraction::fromPayload($request->json()->all());

        return response()->json(match (true) {
            $interaction->type === DiscordInteraction::PING => DiscordReply::pong(),
            $interaction->type === DiscordInteraction::COMPONENT => $resolve($interaction),
            $interaction->subcommand === 'pedir' => $ask($interaction),
            $interaction->subcommand === 'vincular' => $this->link($interaction, $link),
            default => DiscordReply::message(self::HELP),
        });
    }

    /** @return array<string, mixed> */
    private function link(DiscordInteraction $interaction, LinkDiscordAccount $link): array
    {
        try {
            $user = $link($interaction->option('codigo'), (string) $interaction->discordUserId);
        } catch (BusinessRuleViolation $e) {
            return DiscordReply::message($e->getMessage());
        }

        return DiscordReply::message("Listo: tu Discord quedó vinculado a {$user->fullName()} en newDesk. Probá /newdesk pedir pedido:¿qué bolsas se están por agotar?");
    }
}
