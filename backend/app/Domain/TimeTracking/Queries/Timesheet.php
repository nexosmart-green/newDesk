<?php

namespace App\Domain\TimeTracking\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Carbon;

/**
 * RF-05.12 "Mi semana": the person's entries between two local dates, grouped by local day with totals.
 * Comments are included (RF-05.18: they explain what the client's hours were used for).
 */
final class Timesheet
{
    /** @return array{timezone: string, total_seconds: int, days: list<array{date: string, total_seconds: int, entries: list<array<string, mixed>>}>} */
    public function __invoke(User $user, string $fromDate, string $toDate): array
    {
        $timezone = $user->effectiveTimezone();
        $from = Carbon::parse($fromDate, $timezone)->startOfDay();
        $to = Carbon::parse($toDate, $timezone)->endOfDay();

        $entries = TimeEntry::query()->forCompany($user->company_id)
            ->where('user_id', $user->id)
            ->whereBetween('started_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->with(['task:id,code,title', 'project:id,name'])
            ->orderBy('started_at')
            ->get();

        $days = [];
        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $days[$day->toDateString()] = ['date' => $day->toDateString(), 'total_seconds' => 0, 'entries' => []];
        }

        foreach ($entries as $entry) {
            $date = $entry->started_at->copy()->setTimezone($timezone)->toDateString();
            $seconds = $entry->elapsedSeconds();
            $days[$date]['total_seconds'] += $seconds;
            $days[$date]['entries'][] = [
                'id' => $entry->id,
                'task' => ['code' => $entry->task->code, 'title' => $entry->task->title],
                'project' => $entry->project->name,
                'started_at' => $entry->started_at->toIso8601String(),
                'ended_at' => $entry->ended_at?->toIso8601String(),
                'seconds' => $seconds,
                'is_running' => $entry->isRunning(),
                'comment' => $entry->comment,
                'comment_pending' => $entry->comment_pending,
                'source' => $entry->source->value,
            ];
        }

        return [
            'timezone' => $timezone,
            'total_seconds' => array_sum(array_column($days, 'total_seconds')),
            'days' => array_values($days),
        ];
    }
}
