<?php

namespace App\Domain\Monitoring\Contracts;

use App\Domain\Monitoring\Discord\DiscordError;
use App\Domain\Monitoring\Discord\DiscordMessage;

/**
 * Discord as the "Monitoreo" bot (RF-13.09, monitoreo internal/panel/discord, D-059, D-060). Only
 * the channels of the allowed categories exist for the panel. Every call throws DiscordError with a
 * message worded for the team; without a bot token, DiscordError::notConfigured().
 */
interface DiscordGateway
{
    public function configured(): bool;

    /** @throws DiscordError */
    public function send(DiscordMessage $message): void;

    /**
     * The text channels and active threads of the allowed categories, with whether the bot can post.
     *
     * @return list<array{id: string, name: string, kind: string, category: string|null, can_send: bool}>
     *
     * @throws DiscordError
     */
    public function channels(): array;

    /**
     * The people of the server, bots left out (needs the Server Members intent).
     *
     * @return list<array{id: string, username: string, display_name: string}>
     *
     * @throws DiscordError
     */
    public function members(): array;

    /** One of the listed channels: the only places the panel posts to. */
    public function allowed(string $channelId): bool;

    /**
     * A text channel in DISCORD_CATEGORY_ID, inheriting the category's permissions.
     *
     * @return array{id: string, name: string}
     *
     * @throws DiscordError
     */
    public function createChannel(string $name, string $topic): array;
}
