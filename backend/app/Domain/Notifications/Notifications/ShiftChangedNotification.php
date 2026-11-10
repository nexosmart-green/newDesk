<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Enums\NotificationEvent;
use Illuminate\Support\Carbon;

/** RF-09.07 / RF-06.08 */
final class ShiftChangedNotification extends AppNotification
{
    /** @param  list<string>  $dates */
    public function __construct(private readonly array $dates, private readonly string $changedBy) {}

    public function event(): NotificationEvent
    {
        return NotificationEvent::ShiftChanged;
    }

    protected function title(User $notifiable): string
    {
        return 'Cambiaron tus turnos';
    }

    protected function body(User $notifiable): string
    {
        $dates = $this->dates;
        sort($dates);
        $first = Carbon::parse($dates[0])->format('d/m');
        $last = Carbon::parse(end($dates))->format('d/m');
        $range = $first === $last ? "el {$first}" : "entre el {$first} y el {$last}";

        return "{$this->changedBy} actualizó tus turnos {$range}. Revisá tu calendario.";
    }

    protected function path(): string
    {
        return '/calendario';
    }
}
