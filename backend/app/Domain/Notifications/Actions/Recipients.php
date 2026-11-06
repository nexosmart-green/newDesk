<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use Illuminate\Support\Collection;

/**
 * Who receives company-level notifications. Only active people.
 */
final class Recipients
{
    /** @return Collection<int, User> the company Admins (system role) */
    public function admins(int $companyId): Collection
    {
        return User::query()->where('company_id', $companyId)->where('status', UserStatus::Active)
            ->whereHas('role', fn ($role) => $role->withoutGlobalScopes()->where('is_system', true))
            ->get();
    }

    /** @return Collection<int, User> */
    public function withPermission(int $companyId, Permission $permission): Collection
    {
        return User::query()->where('company_id', $companyId)->where('status', UserStatus::Active)->with('role')->get()
            ->filter(fn (User $user) => $user->hasPermission($permission))->values();
    }

    /** RF-09.04: Admin + project leader, without duplicates. @return Collection<int, User> */
    public function adminsAndLeader(Project $project): Collection
    {
        $people = $this->admins($project->company_id);
        if ($project->leader_user_id !== null && ! $people->contains('id', $project->leader_user_id)) {
            $leader = User::query()->where('status', UserStatus::Active)->find($project->leader_user_id);
            if ($leader !== null) {
                $people->push($leader);
            }
        }

        return $people;
    }
}
