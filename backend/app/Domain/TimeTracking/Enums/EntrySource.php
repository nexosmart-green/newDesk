<?php

namespace App\Domain\TimeTracking\Enums;

enum EntrySource: string
{
    case Timer = 'timer';
    case Manual = 'manual';
    case Migration = 'migration';
}
