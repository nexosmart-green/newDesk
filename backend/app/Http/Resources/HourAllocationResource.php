<?php

namespace App\Http\Resources;

use App\Domain\TimeTracking\Models\HourAllocation;
use Illuminate\Http\Request;

/**
 * One movement of a project's hour bag (RF-05.11 "Horas" tab): who loaded how much and why.
 *
 * @mixin HourAllocation
 */
class HourAllocationResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seconds' => $this->seconds,
            'reason' => $this->reason,
            'reference' => $this->reference,
            'created_by' => $this->creator->fullName(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
