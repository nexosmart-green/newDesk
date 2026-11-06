<?php

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use Illuminate\Http\Request;

/**
 * @mixin Project
 */
class ProjectResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $person = fn (?User $user) => $user === null ? null : ['id' => $user->id, 'full_name' => $user->fullName()];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code_prefix' => $this->code_prefix,
            'description' => $this->description,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->id, 'name' => $this->client->name]),
            'leader' => $this->whenLoaded('leader', fn () => $person($this->leader)),
            'members' => $this->whenLoaded('members', fn () => $this->members->map($person)->values()),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
