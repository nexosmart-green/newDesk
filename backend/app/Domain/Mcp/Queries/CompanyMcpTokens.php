<?php

namespace App\Domain\Mcp\Queries;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * RF-12.05: every MCP token issued to the people of a company, newest first, with its owner loaded.
 */
final class CompanyMcpTokens
{
    /** @return Collection<int, PersonalAccessToken> */
    public function __invoke(int $companyId): Collection
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', User::query()->where('company_id', $companyId)->select('id'))
            ->with('tokenable')
            ->latest('id')
            ->get();
    }
}
