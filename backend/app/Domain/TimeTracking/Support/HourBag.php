<?php

namespace App\Domain\TimeTracking\Support;

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Carbon;

/**
 * Read model of a project's hour bag (RF-05 "Conceptos"), all in integer seconds (R-5):
 *   Bolsa = Σ allocations · Consumidas = closed entries + running timers up to "now" · Disponibles = Bolsa − Consumidas.
 * Internal projects (RF-05.14) have no cap: they never block but still count.
 */
final class HourBag
{
    private function __construct(
        public readonly Project $project,
        public readonly int $runningSeconds,
    ) {}

    public static function of(Project $project, ?Carbon $now = null): self
    {
        $now ??= now();
        $running = TimeEntry::query()->forCompany($project->company_id)
            ->where('project_id', $project->id)->running()
            ->get(['id', 'started_at', 'seconds'])
            ->sum(fn (TimeEntry $entry) => $entry->elapsedSeconds($now));

        return new self($project, (int) $running);
    }

    public function hasCap(): bool
    {
        return $this->project->type->hasHourCap();
    }

    public function allocated(): int
    {
        return $this->project->allocated_seconds;
    }

    public function consumed(): int
    {
        return $this->project->consumed_seconds + $this->runningSeconds;
    }

    /** null = no cap (internal project). */
    public function available(): ?int
    {
        return $this->hasCap() ? $this->allocated() - $this->consumed() : null;
    }

    /** RF-05.05: with nothing left, no timer starts and no manual hours are accepted. */
    public function isExhausted(): bool
    {
        return $this->hasCap() && $this->available() <= 0;
    }

    public function percentConsumed(): ?int
    {
        if (! $this->hasCap() || $this->allocated() <= 0) {
            return $this->hasCap() && $this->consumed() > 0 ? 100 : null;
        }

        return (int) floor($this->consumed() * 100 / $this->allocated());
    }

    /** @return array<string, int|bool|null> */
    public function toArray(): array
    {
        return [
            'has_cap' => $this->hasCap(),
            'allocated_seconds' => $this->hasCap() ? $this->allocated() : null,
            'consumed_seconds' => $this->consumed(),
            'running_seconds' => $this->runningSeconds,
            'available_seconds' => $this->available(),
            'percent_consumed' => $this->percentConsumed(),
            'is_exhausted' => $this->isExhausted(),
        ];
    }
}
