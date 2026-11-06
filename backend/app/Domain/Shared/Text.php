<?php

namespace App\Domain\Shared;

/**
 * Text a client typed or an app sent, handled as monitoreo does (cleanText, clip, oneLine).
 */
final class Text
{
    /** Drops control characters (keeping line breaks and tabs) and trims. */
    public static function clean(string $text): string
    {
        return trim((string) preg_replace('/[^\P{Cc}\n\t]/u', '', str_replace("\r\n", "\n", mb_scrub($text))));
    }

    /** At most $limit characters, with "…" when it was cut. */
    public static function clip(string $text, int $limit): string
    {
        return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit).'…';
    }

    /** Keeps a client's text out of mail headers: one line, at most $limit characters. */
    public static function oneLine(string $text, int $limit): string
    {
        return self::clip(implode(' ', preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []), $limit);
    }
}
