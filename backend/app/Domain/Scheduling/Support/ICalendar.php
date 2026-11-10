<?php

namespace App\Domain\Scheduling\Support;

use Illuminate\Support\Carbon;

/**
 * Minimal RFC 5545 writer for the personal feed (shifts as timed events, due dates and holidays as all-day).
 */
final class ICalendar
{
    /** @var list<string> */
    private array $events = [];

    public function __construct(private readonly string $name) {}

    public function timed(string $uid, string $summary, Carbon $startUtc, Carbon $endUtc): self
    {
        $this->events[] = $this->event($uid, $summary, [
            'DTSTART:'.$startUtc->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$endUtc->copy()->utc()->format('Ymd\THis\Z'),
        ]);

        return $this;
    }

    public function allDay(string $uid, string $summary, string $date): self
    {
        $day = Carbon::parse($date);
        $this->events[] = $this->event($uid, $summary, [
            'DTSTART;VALUE=DATE:'.$day->format('Ymd'),
            'DTEND;VALUE=DATE:'.$day->copy()->addDay()->format('Ymd'),
        ]);

        return $this;
    }

    public function render(): string
    {
        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//newDesk//Calendario//ES',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.$this->escape($this->name),
            ...$this->events,
            'END:VCALENDAR',
        ])."\r\n";
    }

    /** @param  list<string>  $dates */
    private function event(string $uid, string $summary, array $dates): string
    {
        return implode("\r\n", [
            'BEGIN:VEVENT',
            "UID:{$uid}",
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            ...$dates,
            'SUMMARY:'.$this->escape($summary),
            'END:VEVENT',
        ]);
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }
}
