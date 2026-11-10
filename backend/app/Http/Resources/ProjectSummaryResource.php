<?php

namespace App\Http\Resources;

use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Support\HourBag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Project row for lists (RF-03.07). Hours (allocated/consumed/%) are added by E4.
 *
 * @mixin Project
 */
class ProjectSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code_prefix' => $this->code_prefix,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'client' => $this->whenLoaded('client', fn () => $this->client ? ['id' => $this->client->id, 'name' => $this->client->displayName()] : null),
            'leader' => $this->whenLoaded('leader', fn () => $this->leader ? ['id' => $this->leader->id, 'full_name' => $this->leader->fullName()] : null),
            'members_count' => $this->whenCounted('members'),
            'hours' => HourBag::of($this->resource)->toArray(),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
        ];
    }
}
