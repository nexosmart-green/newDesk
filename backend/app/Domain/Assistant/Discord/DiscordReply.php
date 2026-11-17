<?php

namespace App\Domain\Assistant\Discord;

/**
 * Interaction responses. Everything the assistant shows in Discord is ephemeral (only the person who asked sees it:
 * it carries clients and hours, CA-14.09) and mentions nobody.
 */
final class DiscordReply
{
    private const PONG = 1;

    private const MESSAGE = 4;

    private const DEFERRED_MESSAGE = 5;

    private const UPDATE_MESSAGE = 7;

    public const EPHEMERAL = 64;

    /** @return array<string, mixed> */
    public static function pong(): array
    {
        return ['type' => self::PONG];
    }

    /** @return array<string, mixed> */
    public static function message(string $content): array
    {
        return ['type' => self::MESSAGE, 'data' => self::body($content, [])];
    }

    /**
     * "Pensando…": the answer comes later, editing this same message (Discord allows 3 s to answer).
     *
     * @return array<string, mixed>
     */
    public static function thinking(): array
    {
        return ['type' => self::DEFERRED_MESSAGE, 'data' => ['flags' => self::EPHEMERAL]];
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    public static function update(string $content, array $components): array
    {
        return ['type' => self::UPDATE_MESSAGE, 'data' => self::body($content, $components)];
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    public static function body(string $content, array $components): array
    {
        return [
            'content' => DiscordAnswerMessage::clip($content),
            'flags' => self::EPHEMERAL,
            'components' => $components,
            'allowed_mentions' => ['parse' => []],
        ];
    }
}
