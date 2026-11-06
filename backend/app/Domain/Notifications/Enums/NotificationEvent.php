<?php

namespace App\Domain\Notifications\Enums;

/**
 * RF-09 catalog. Every event always lands in the app (bell); e-mail is a per-user preference.
 */
enum NotificationEvent: string
{
    case TaskAssigned = 'task_assigned';          // RF-09.01
    case Mentioned = 'mentioned';                 // RF-09.02
    case TaskDue = 'task_due';                    // RF-09.03 (e-mail as daily summary)
    case HoursThreshold = 'hours_threshold';      // RF-09.04
    case TimerCut = 'timer_cut';                  // RF-09.05
    case ExtensionRequested = 'extension_requested'; // RF-09.06
    case ShiftChanged = 'shift_changed';          // RF-09.07

    public function label(): string
    {
        return match ($this) {
            self::TaskAssigned => 'Te asignaron una tarea',
            self::Mentioned => 'Te mencionaron en un comentario',
            self::TaskDue => 'Tareas que vencen mañana o vencidas (resumen diario)',
            self::HoursThreshold => 'Proyecto al 80% / 100% de horas',
            self::TimerCut => 'Tu cronómetro se cortó',
            self::ExtensionRequested => 'Pedido de ampliación de horas',
            self::ShiftChanged => 'Te asignaron o cambiaron un turno',
        };
    }

    /** RF-09: hour-block e-mails cannot be turned off by an Admin. */
    public function isMandatoryForAdmins(): bool
    {
        return $this === self::HoursThreshold || $this === self::TimerCut;
    }
}
