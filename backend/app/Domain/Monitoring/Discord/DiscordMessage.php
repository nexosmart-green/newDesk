<?php

namespace App\Domain\Monitoring\Discord;

/**
 * One post (monitoreo discord.Message). Only the users in `mention` can be pinged: allowed_mentions
 * never parses @everyone, roles or ids written in the text (a client's report included).
 */
final class DiscordMessage
{
    /** Room under Discord's 2000-character limit. */
    public const MAX_CONTENT = 1900;

    /** Escapes Discord formatting, masked links, <@…>/<t:…> tokens and @mentions (D-078, H17). */
    private const ESCAPES = ['\\' => '\\\\', '*' => '\*', '_' => '\_', '~' => '\~', '`' => '\`', '|' => '\|', '>' => '\>', '#' => '\#',
        '[' => '\[', ']' => '\]', '(' => '\(', ')' => '\)', '<' => '\<', '@' => '\@'];

    /**
     * @param  list<string>  $mention  Discord user ids that may be pinged
     * @param  bool  $priority  a server, its agent or a service down: never behind a flood of project notices (D-107)
     */
    public function __construct(
        public readonly string $channelId,
        public readonly string $content,
        public readonly array $mention = [],
        public readonly bool $priority = false,
    ) {}

    /** A Discord id (user, channel, server) and nothing else. */
    public static function validId(?string $raw): bool
    {
        return $raw !== null && preg_match('/^\d{17,20}$/', $raw) === 1;
    }

    public static function mention(string $userId): string
    {
        return "<@{$userId}>";
    }

    public static function channelLink(string $channelId): string
    {
        return "<#{$channelId}>";
    }

    /** Keeps names and lower-trust text (a client's, an app's, an agent's) from turning into formatting. */
    public static function escape(string $text): string
    {
        return strtr($text, self::ESCAPES);
    }

    /** At most MAX_CONTENT characters, the last one "…" when cut. */
    public function clippedContent(): string
    {
        return mb_strlen($this->content) > self::MAX_CONTENT ? mb_substr($this->content, 0, self::MAX_CONTENT - 1).'…' : $this->content;
    }
}
