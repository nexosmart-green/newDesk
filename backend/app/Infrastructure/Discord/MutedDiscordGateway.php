<?php

namespace App\Infrastructure\Discord;

use App\Domain\Monitoring\Contracts\DiscordGateway;
use App\Domain\Monitoring\Discord\DiscordError;
use App\Domain\Monitoring\Discord\DiscordMessage;

/** No DISCORD_BOT_TOKEN: nothing is listed, accepted or sent (monitoreo's unconfigured client). */
final class MutedDiscordGateway implements DiscordGateway
{
    public function configured(): bool
    {
        return false;
    }

    public function send(DiscordMessage $message): void
    {
        throw DiscordError::notConfigured();
    }

    public function channels(): array
    {
        throw DiscordError::notConfigured();
    }

    public function members(): array
    {
        throw DiscordError::notConfigured();
    }

    public function allowed(string $channelId): bool
    {
        return false;
    }

    public function createChannel(string $name, string $topic): array
    {
        throw DiscordError::notConfigured();
    }
}
