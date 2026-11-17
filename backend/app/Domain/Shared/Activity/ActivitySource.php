<?php

namespace App\Domain\Shared\Activity;

enum ActivitySource: string
{
    case Web = 'web';
    case Mcp = 'mcp';
    /** RF-14.04: confirmed proposals of the AI assistant; the channel (web/discord) goes in token_name. */
    case Assistant = 'assistant';
    /** Jobs and automatic cuts (no human actor in the request). */
    case System = 'system';
}
