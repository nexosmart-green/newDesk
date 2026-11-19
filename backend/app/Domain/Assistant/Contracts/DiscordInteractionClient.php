<?php

namespace App\Domain\Assistant\Contracts;

/**
 * Discord's side of /newdesk (RF-14.08), on monitoreo's app. Adapter: App\Infrastructure\Discord\DiscordInteractionHttpClient.
 */
interface DiscordInteractionClient
{
    /**
     * Replaces the deferred "pensando…" message of an interaction (its token lives 15 minutes).
     *
     * @param  list<array<string, mixed>>  $components
     * @param  list<array{name: string, contents: string}>  $files
     */
    public function editOriginal(string $interactionToken, string $content, array $components, array $files = []): void;

    /**
     * Posts a new message in a channel with the bot token (the answer to a DM, RF-14.08 phase 5).
     *
     * @param  list<array<string, mixed>>  $components
     * @param  list<array{name: string, contents: string}>  $files
     */
    public function createMessage(string $channelId, string $content, array $components = [], array $files = []): void;

    /** "Escribiendo…" in a channel for a few seconds, while the turn is queued. */
    public function typing(string $channelId): void;

    /** Creates or updates the guild's slash commands with the bot token. @param list<array<string, mixed>> $commands */
    public function registerCommands(array $commands): void;
}
