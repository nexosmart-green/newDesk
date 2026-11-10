<?php

namespace App\Http\Resources;

use App\Domain\Projects\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Project detail (RF-03.04 "Resumen" header).
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['client', 'leader', 'members.profile.jobTitle', 'members.profile.area']);

        return [
            ...ProjectSummaryResource::make($this->resource)->toArray($request),
            'description' => $this->description,
            'prefix_locked' => $this->prefixIsLocked(),
            'members' => PersonResource::collection($this->members),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'manage_members' => $request->user()?->can('manageMembers', $this->resource) ?? false,
            ],
        ];
    }
}
