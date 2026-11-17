<?php

namespace App\Domain\TimeTracking\Enums;

enum EntrySource: string
{
    case Timer = 'timer';
    case Manual = 'manual';
    case Migration = 'migration';
    // RF-05.19: a part of a split day clock.
    case DayClock = 'day_clock';

    public function label(): string
    {
        return match ($this) {
            self::Timer => 'Cronómetro',
            self::Manual => 'Manual',
            self::Migration => 'Migración',
            self::DayClock => 'Reloj del día',
        };
    }
}
