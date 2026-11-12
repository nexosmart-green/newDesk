<?php

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * @mixin User
 */
class CurrentUserResource extends ApiResource
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
            'locale' => $this->locale,
            'timezone' => $this->effectiveTimezone(),
            'role' => ['id' => $this->role->id, 'name' => $this->role->name],
            'permissions' => $this->isActive() ? $this->role->permissionValues() : [],
            'two_factor' => [
                'enabled' => $this->hasTwoFactorEnabled(),
                'required' => $this->requiresTwoFactor(),
            ],
            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'currency' => $this->company->currency,
                'timezone' => $this->company->timezone,
            ],
        ];
    }
}
