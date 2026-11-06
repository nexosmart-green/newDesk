<?php

namespace App\Domain\Scheduling\Enums;

enum WorkMode: string
{
    case Office = 'office';
    case Remote = 'remote';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Office => 'Oficina',
            self::Remote => 'Remoto',
            self::Hybrid => 'Híbrido',
        };
    }
}
