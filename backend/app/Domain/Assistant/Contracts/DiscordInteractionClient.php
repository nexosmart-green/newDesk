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

    /** Creates or updates the guild's slash commands with the bot token. @param list<array<string, mixed>> $commands */
    public function registerCommands(array $commands): void;
}
