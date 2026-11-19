<?php

namespace App\Domain\Assistant\Listeners;

use App\Domain\Assistant\Actions\GenerateAssistantReport;
use App\Domain\Assistant\Contracts\DiscordInteractionClient;
use App\Domain\Assistant\Discord\DiscordAnswerMessage;
use App\Domain\Assistant\Discord\DiscordDirectChannels;
use App\Domain\Assistant\Discord\DiscordInteractionTokens;
use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Events\AssistantTurnFinished;
use App\Domain\Assistant\Models\AssistantFile;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Monitoring\Jobs\SendDiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * RF-14.08: a turn asked from Discord answers by editing its "pensando…" message (/newdesk pedir) or with a new
 * message in the DM it was asked in (phase 5): the text, a Confirmar / Cancelar row per proposal and the reports as
 * attachments. Queued on monitoreo's ordered `discord` queue, so Discord being
 * slow never holds the assistant's single worker; Discord being down never breaks anything: the answer is also
 * in the web chat.
 */
final class RelayAnswerToDiscord implements ShouldQueue
{
    public int $tries = 1;

    public function __construct(
        private readonly DiscordInteractionTokens $tokens,
        private readonly DiscordDirectChannels $directChannels,
        private readonly DiscordInteractionClient $discord,
    ) {}

    public function viaQueue(): string
    {
        return SendDiscordMessage::QUEUE;
    }

    public function handle(AssistantTurnFinished $event): void
    {
        try {
            $answer = AssistantMessage::query()->withoutGlobalScopes()->with(['conversation', 'proposals', 'files'])->find($event->answerId);
            if ($answer?->conversation->channel !== AssistantChannel::Discord) {
                return;
            }
            $token = $this->tokens->pull($answer->id);
            $channelId = $token === null ? $this->directChannels->pull($answer->id) : null;
            if ($token === null && $channelId === null) {
                return; // more than 15 minutes went by: the answer is in the web chat
            }

            $message = DiscordAnswerMessage::of($answer);
            $files = $answer->files->map(fn (AssistantFile $file) => [
                'name' => $file->name, 'contents' => (string) Storage::disk(GenerateAssistantReport::DISK)->get($file->path),
            ])->values()->all();
            $token !== null
                ? $this->discord->editOriginal($token, $message['content'], $message['components'], $files)
                : $this->discord->createMessage((string) $channelId, $message['content'], $message['components'], $files);
        } catch (Throwable $e) {
            report($e); // never with the token: the client's errors carry no URL
        }
    }
}
