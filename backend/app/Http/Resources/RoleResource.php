<?php

namespace App\Http\Resources;

use App\Domain\Identity\Models\Role;
use Illuminate\Http\Request;

/**
 * @mixin Role
 */
class RoleResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_system' => $this->is_system,
            'permissions' => $this->permissionValues(),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
