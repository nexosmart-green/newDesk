<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * URL for logs: no query string, and secrets that travel in the path (invitation and iCal tokens) redacted,
 * so the errors log served by /api/errors never contains usable credentials.
 */
final class SafeRequestUrl
{
    private const PATTERNS = [
        '#/invitations/[^/?]+#' => '/invitations/[redacted]',
        '#/calendar/[^/?]+\.ics#' => '/calendar/[redacted].ics',
    ];

    public static function of(Request $request): string
    {
        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $request->url());
    }
}
