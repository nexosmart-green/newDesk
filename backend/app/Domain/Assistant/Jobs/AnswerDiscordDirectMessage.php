<?php

namespace App\Domain\Assistant\Jobs;

use App\Domain\Assistant\Actions\AskAssistant;
use App\Domain\Assistant\Contracts\DiscordInteractionClient;
use App\Domain\Assistant\Discord\DiscordAskers;
use App\Domain\Assistant\Discord\DiscordConversations;
use App\Domain\Assistant\Discord\DiscordDirectChannels;
use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Monitoring\Jobs\SendDiscordMessage;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * RF-14.08 phase 5: a DM to the bot, queued by the gateway process (which never does HTTP nor touches the database).
 * Same rules as `/newdesk pedir`: only a linked team user with `assistant.use` gets a turn, anyone else a plain
 * answer and nothing runs. The answer comes later as a new message in the DM (RelayAnswerToDiscord).
 */
final class AnswerDiscordDirectMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const NOT_LINKED = 'Tu cuenta de Discord no está vinculada a newDesk. Entrá a newDesk → Asistente → Conectar Discord, copiá el código y escribí /newdesk vincular codigo:TU-CÓDIGO en el servidor de la empresa.';

    public const FAILED = 'No pude procesar tu mensaje. Probá de nuevo en un rato o usá el asistente en newDesk.';

    public const TOO_FAST = 'Mandaste muchos mensajes seguidos: esperá un minuto y volvé a escribir.';

    /** Per Discord account, whoever writes: the gateway answers strangers too. */
    private const PER_MINUTE = 6;

    public int $tries = 1;

    public function __construct(public readonly string $authorId, public readonly string $channelId, public readonly string $content)
    {
        $this->onQueue(SendDiscordMessage::QUEUE);
    }

    public function handle(DiscordAskers $askers, AskAssistant $ask, DiscordConversations $conversations, DiscordDirectChannels $channels, DiscordInteractionClient $discord): void
    {
        try {
            if (! RateLimiter::attempt("assistant-discord-dm|{$this->authorId}", self::PER_MINUTE, fn () => true)) {
                // Said once: past the limit plus one, silence.
                if (RateLimiter::attempts("assistant-discord-dm|{$this->authorId}") === self::PER_MINUTE) {
                    RateLimiter::hit("assistant-discord-dm|{$this->authorId}");
                    $discord->createMessage($this->channelId, self::TOO_FAST);
                }

                return;
            }
            $user = $askers->find($this->authorId);
            if ($user === null) {
                $discord->createMessage($this->channelId, self::NOT_LINKED);

                return;
            }
            Auth::setUser($user); // company scopes and policies see who is asking, as in a web request
            if (! $user->hasPermission(Permission::AssistantUse)) {
                $discord->createMessage($this->channelId, 'Tu rol no tiene habilitado el asistente de IA.');

                return;
            }

            try {
                // The channel is kept before the turn is queued: a turn that ends at once still finds it.
                $ask($user, $this->content, AssistantChannel::Discord, $conversations->current($user),
                    fn (AssistantMessage $answer) => $channels->remember($answer->id, $this->channelId));
            } catch (BusinessRuleViolation $e) {
                $discord->createMessage($this->channelId, $e->getMessage());

                return;
            }
            $this->typing($discord);
        } catch (Throwable $e) {
            report($e);
            $this->tell($discord, self::FAILED);
        } finally {
            Auth::forgetUser();
        }
    }

    /** Best effort: when Discord itself is what failed, there is nobody to tell. */
    private function tell(DiscordInteractionClient $discord, string $text): void
    {
        try {
            $discord->createMessage($this->channelId, $text);
        } catch (Throwable) {
        }
    }

    private function typing(DiscordInteractionClient $discord): void
    {
        try {
            $discord->typing($this->channelId);
        } catch (Throwable) {
            // Cosmetic: the answer comes anyway.
        }
    }
}
