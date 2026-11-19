<?php

namespace App\Http\Resources;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * monitoreo's store.User JSON (team users list, a client's portal users). `is_admin` is monitoreo's
 * admin: monitoring.manage. Needs `role` (team) or `client` (client users) loaded.
 *
 * @mixin User
 */
class MonitoringUserResource extends ApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->fullName(),
            'is_admin' => $this->client_id === null && $this->hasPermission(Permission::MonitoringManage),
            'active' => $this->status === UserStatus::Active,
            'totp_enabled' => $this->two_factor_confirmed_at !== null,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'last_login_at' => $this->last_login_at?->toIso8601ZuluString(),
            'discord_user_id' => $this->discord_user_id,
            // The person linked it themselves with a code (RF-14.08): only then the assistant acts for them from Discord.
            'discord_verified' => $this->discord_user_id !== null && $this->discord_verified_at !== null,
            'client' => $this->client_id === null ? null : ['id' => $this->client_id, 'name' => $this->client->displayName()],
        ];
    }
}
