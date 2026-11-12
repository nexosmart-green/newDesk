<?php

namespace App\Http\Resources;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * An MCP personal access token as listed: never the secret (RF-12.02). `owner` only when `tokenable` is loaded
 * (the company-wide listing, RF-12.05).
 *
 * @mixin PersonalAccessToken
 */
class McpTokenResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'owner' => $this->whenLoaded('tokenable', fn (User $owner) => ['id' => $owner->id, 'full_name' => $owner->fullName()]),
        ];
    }
}
