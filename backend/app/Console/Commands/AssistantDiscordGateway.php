<?php

namespace App\Console\Commands;

use App\Domain\Assistant\Discord\DirectMessage;
use App\Domain\Assistant\Jobs\AnswerDiscordDirectMessage;
use App\Infrastructure\Discord\DiscordGatewayRunner;
use Illuminate\Console\Command;

/**
 * RF-14.08 phase 5: keeps the bot connected to Discord's gateway so people can DM the assistant. Runs under
 * Supervisord (supervisor:sync adds it once the bot and the app are configured). Each DM becomes a queued job.
 */
class AssistantDiscordGateway extends Command
{
    protected $signature = 'assistant:discord-gateway';

    protected $description = 'Listen to DMs to the bot and hand them to the assistant (Discord gateway)';

    public function handle(): int
    {
        if (! self::configured()) {
            $this->error('Faltan DISCORD_BOT_TOKEN y DISCORD_APPLICATION_ID.');

            return self::FAILURE;
        }

        $exitCode = (new DiscordGatewayRunner((string) config('newdesk.monitoring.discord.bot_token'), function (string $event, array $data) {
            $message = $event === 'MESSAGE_CREATE' ? DirectMessage::fromEvent($data) : null;
            if ($message !== null) {
                AnswerDiscordDirectMessage::dispatch($message->authorId, $message->channelId, $message->content);
            }
        }))->run();

        if ($exitCode !== self::SUCCESS) {
            // Discord refused the token or the intents: supervisor restarts us, so wait before trying again instead of
            // identifying with a bad token in a tight loop (Discord limits identifies and flags such tokens).
            sleep(self::REFUSED_PAUSE_SECONDS);
        }

        return $exitCode;
    }

    private const REFUSED_PAUSE_SECONDS = 300;

    public static function configured(): bool
    {
        return filled(config('newdesk.monitoring.discord.bot_token')) && filled(config('newdesk.assistant.discord.application_id'));
    }
}
