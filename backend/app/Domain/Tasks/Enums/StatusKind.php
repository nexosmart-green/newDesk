<?php

namespace App\Domain\Tasks\Enums;

/**
 * What the rules read from a configurable status (RF-04.03).
 */
enum StatusKind: string
{
    case Open = 'open';
    case Blocked = 'blocked';
    /** CA-04.03: moving a task here stops its timers. Hidden by default in lists (RF-04.05). */
    case Closed = 'closed';
}
