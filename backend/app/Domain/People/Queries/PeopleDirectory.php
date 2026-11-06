<?php

namespace App\Domain\People\Queries;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Company directory: search by name, area, and skill ("¿quién sabe React?", RF-01.06).
 * Only active people by default: inactive ones disappear from selectors (RF-01.10).
 */
final class PeopleDirectory
{
    /**
     * @param  array{search?: string|null, area_id?: int|null, skill_id?: int|null, min_level?: int|null, include_inactive?: bool}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function __invoke(int $companyId, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $skillId = $filters['skill_id'] ?? null;

        return User::query()
            ->where('company_id', $companyId)
            ->with(['profile.jobTitle', 'profile.area'])
            ->when(! ($filters['include_inactive'] ?? false), fn (Builder $query) => $query->where('status', UserStatus::Active))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['area_id'] ?? null, fn (Builder $query, int $areaId) => $query
                ->whereHas('profile', fn (Builder $profile) => $profile->where('area_id', $areaId)))
            ->when($skillId, fn (Builder $query) => $query
                ->whereHas('skills', fn (Builder $skills) => $skills
                    ->where('skills.id', $skillId)
                    ->where('user_skills.level', '>=', $filters['min_level'] ?? 0))
                ->with(['skills' => fn ($skills) => $skills->where('skills.id', $skillId)]))
            ->orderBy('first_name')->orderBy('last_name')
            ->paginate($perPage);
    }
}
