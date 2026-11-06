<?php

namespace App\Domain\Projects\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    /** RF-03.08: closed projects accept no new tasks nor timers. */
    public function isClosed(): bool
    {
        return $this === self::Finished || $this === self::Cancelled;
    }

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
