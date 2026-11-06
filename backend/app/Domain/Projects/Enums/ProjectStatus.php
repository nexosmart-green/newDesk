<?php

namespace App\Domain\Projects\Enums;

/** RF-03.01. */
enum ProjectStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Paused => 'En pausa',
            self::Finished => 'Finalizado',
            self::Cancelled => 'Cancelado',
        };
    }
}
