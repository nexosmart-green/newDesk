<?php

namespace App\Domain\Assistant\Discord;

/**
 * What Discord sent to /api/discord/interactions (already signature-checked), reduced to what the assistant uses.
 * https://discord.com/developers/docs/interactions/receiving-and-responding
 */
final class DiscordInteraction
{
    public const PING = 1;

    public const COMMAND = 2;

    public const COMPONENT = 3;

    /** @param  array<string, string>  $options */
    private function __construct(
        public readonly int $type,
        public readonly ?string $discordUserId,
        public readonly string $token,
        public readonly ?string $subcommand,
        public readonly array $options,
        public readonly ?string $customId,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public static function fromPayload(array $payload): self
    {
        $sub = $payload['data']['options'][0] ?? null;
        $options = [];
        foreach ((array) ($sub['options'] ?? []) as $option) {
            $options[(string) ($option['name'] ?? '')] = trim((string) ($option['value'] ?? ''));
        }

        return new self(
            type: (int) ($payload['type'] ?? 0),
            // In a server the person is `member.user`; in a DM, `user`.
            discordUserId: $payload['member']['user']['id'] ?? $payload['user']['id'] ?? null,
            token: (string) ($payload['token'] ?? ''),
            subcommand: is_array($sub) ? (string) ($sub['name'] ?? '') : null,
            options: $options,
            customId: $payload['data']['custom_id'] ?? null,
        );
    }

    public function option(string $name): string
    {
        return $this->options[$name] ?? '';
    }
}
