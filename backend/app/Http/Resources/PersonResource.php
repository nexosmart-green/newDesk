<?php

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use App\Domain\People\Actions\ReplaceAvatar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public card of a person (directory, assignees, avatars). No sensitive data.
 *
 * @mixin User
 */
class PersonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->relationLoaded('profile') ? $this->profile : null;

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'email' => $this->email,
            'status' => $this->status->value,
            'avatar' => ReplaceAvatar::urls($this->avatar_path),
            'job_title' => $profile?->jobTitle?->only('id', 'name'),
            'area' => $profile?->area?->only('id', 'name'),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'level' => $skill->pivot->level,
            ])->values()),
        ];
    }
}
