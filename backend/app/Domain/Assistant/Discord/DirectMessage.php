<?php

namespace App\Domain\Assistant\Discord;

use App\Domain\Assistant\Models\AssistantMessage;

/**
 * A DM to the bot, from the gateway's MESSAGE_CREATE (RF-14.08 phase 5). Server messages, bots (the assistant's own
 * answers included), system messages and empty ones (only an attachment) are not questions.
 */
final class DirectMessage
{
    /** DEFAULT and REPLY: what a person types. */
    private const TYPES = [0, 19];

    private function __construct(
        public readonly string $authorId,
        public readonly string $channelId,
        public readonly string $content,
    ) {}

    /** @param  array<string, mixed>  $event */
    public static function fromEvent(array $event): ?self
    {
        $author = is_array($event['author'] ?? null) ? $event['author'] : [];
        $content = trim((string) ($event['content'] ?? ''));
        if (isset($event['guild_id']) || ($author['bot'] ?? false) || ! in_array($event['type'] ?? 0, self::TYPES, true)
            || ! is_string($author['id'] ?? null) || ! is_string($event['channel_id'] ?? null) || $content === '') {
            return null;
        }

        return new self($author['id'], $event['channel_id'], mb_substr($content, 0, AssistantMessage::CONTENT_MAX));
    }
}
