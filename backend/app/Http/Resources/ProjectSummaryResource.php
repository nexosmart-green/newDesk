<?php

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\People\Actions\ReplaceAvatar;
use App\Domain\Projects\Models\Project;
use App\Domain\TimeTracking\Support\HourBag;
use Illuminate\Http\Request;

/**
 * Project row for lists (RF-03.07). Hours (allocated/consumed/%) are added by E4.
 *
 * @mixin Project
 */
class ProjectSummaryResource extends ApiResource
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
            'leader' => $this->whenLoaded('leader', fn () => $this->leader ? $this->face($this->leader) : null),
            'members_count' => $this->whenCounted('members'),
            'member_faces' => $this->whenLoaded('members', fn () => $this->members->take(Project::MEMBER_FACES)->map(fn (User $member) => $this->face($member))->values()),
            'hours' => HourBag::of($this->resource)->toArray(),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
        ];
    }

    /** @return array{id: int, full_name: string, avatar: array<string, string>|null} */
    private function face(User $user): array
    {
        return ['id' => $user->id, 'full_name' => $user->fullName(), 'avatar' => ReplaceAvatar::urls($user->avatar_path)];
    }
}
