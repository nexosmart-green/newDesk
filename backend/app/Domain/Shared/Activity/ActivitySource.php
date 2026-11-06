<?php

namespace App\Domain\Shared\Activity;

enum ActivitySource: string
{
    case Web = 'web';
    case Mcp = 'mcp';
    /** Jobs and automatic cuts (no human actor in the request). */
    case System = 'system';
}
