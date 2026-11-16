<?php

namespace App\Domain\Assistant\Enums;

/** An assistant answer is `pending` until the queue picks it up, `running` while Claude Code works. */
enum MessageStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function isInProgress(): bool
    {
        return $this === self::Pending || $this === self::Running;
    }
}
