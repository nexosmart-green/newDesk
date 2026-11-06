<?php

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class CurrentUserResource extends JsonResource
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
            'company' => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'currency' => $this->company->currency,
                'timezone' => $this->company->timezone,
            ],
            // Set for client users, who only reach the portal (RF-13.07).
            'client' => $this->client_id === null ? null : ['id' => $this->client->id, 'name' => $this->client->name],
        ];
    }

    /** @return array<string, mixed> */
    public function with(Request $request): array
    {
        return ['message' => null, 'errors' => null];
    }
}
