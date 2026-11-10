<?php

namespace App\Domain\TimeTracking\Enums;

enum StopReason: string
{
    case User = 'user';
    /** Stopped because the same person started another timer (RF-05.02). */
    case Switch = 'switch';
    case BudgetExhausted = 'budget_exhausted';
    case Forgotten = 'forgotten';
    case TaskClosed = 'task_closed';
    case ProjectClosed = 'project_closed';
    case UserDeactivated = 'user_deactivated';
    case Admin = 'admin';

    /** RF-05.17: cuts nobody chose leave the entry waiting for its comment. */
    public function isAutomatic(): bool
    {
        return in_array($this, [self::BudgetExhausted, self::Forgotten, self::TaskClosed, self::ProjectClosed, self::UserDeactivated, self::Admin], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::User => 'detenido por la persona',
            self::Switch => 'detenido al iniciar otro',
            self::BudgetExhausted => 'sin horas disponibles en el proyecto',
            self::Forgotten => 'timer olvidado',
            self::TaskClosed => 'la tarea se cerró',
            self::ProjectClosed => 'el proyecto se cerró',
            self::UserDeactivated => 'usuario dado de baja',
            self::Admin => 'detenido por un administrador',
        };
    }
}
