<?php

namespace App\Domain\TimeTracking\Enums;

enum EntrySource: string
{
    case Timer = 'timer';
    case Manual = 'manual';
    case Migration = 'migration';

    public function label(): string
    {
        return match ($this) {
            self::Timer => 'Cronómetro',
            self::Manual => 'Manual',
            self::Migration => 'Migración',
        };
    }
}
