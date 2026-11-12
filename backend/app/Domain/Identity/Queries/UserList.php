<?php

namespace App\Domain\Identity\Queries;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * RF-01 user administration list: every user of the company (any status), by name.
 */
final class UserList
{
    /**
     * Fixed page size: unlike the client and people lists, this endpoint has never accepted `per_page`.
     */
    public const PER_PAGE = 50;

    /**
     * @param  array{status?: string|null, search?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function __invoke(Company $company, array $filters): LengthAwarePaginator
    {
        return $company->users()
            ->with('role')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(self::PER_PAGE);
    }
}
