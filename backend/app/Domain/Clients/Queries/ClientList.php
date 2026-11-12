<?php

namespace App\Domain\Clients\Queries;

use App\Domain\Clients\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * RF-02 client list (scoped to the viewer's company by BelongsToCompany), alphabetical by legal name.
 */
final class ClientList
{
    /**
     * @param  array{status?: string|null, search?: string|null}  $filters
     * @return LengthAwarePaginator<int, Client>
     */
    public function __invoke(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return Client::query()
            ->withCount('projects')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner
                ->where('legal_name', 'like', "%{$search}%")
                ->orWhere('trade_name', 'like', "%{$search}%")
                ->orWhere('tax_id', 'like', "%{$search}%")))
            ->orderBy('legal_name')
            ->paginate($perPage);
    }
}
