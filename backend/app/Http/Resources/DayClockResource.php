<?php

namespace App\Http\Resources;

use App\Domain\TimeTracking\Models\DayClock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DayClock */
class DayClockResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'cutoff_at' => $this->cutoffAt()->toIso8601String(),
            'status' => $this->isRunning() ? 'running' : 'pending',
            'elapsed_seconds' => $this->elapsedSeconds(),
            'server_time' => now()->toIso8601String(),
        ];
    }
}
