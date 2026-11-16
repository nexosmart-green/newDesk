<?php

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * User as seen in admin lists. Profile details (RF-01.04) live in E1's profile resource.
 *
 * @mixin User
 */
class UserSummaryResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->fullName(),
            'email' => $this->email,
            'status' => $this->status->value,
            'role' => $this->whenLoaded('role', fn () => ['id' => $this->role->id, 'name' => $this->role->name]),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
