<?php

namespace App\Domain\TimeTracking\Enums;

enum EntrySource: string
{
    case Timer = 'timer';
    case Manual = 'manual';
    case Migration = 'migration';
    // RF-05.19: a part of a split day clock.
    case DayClock = 'day_clock';
}
