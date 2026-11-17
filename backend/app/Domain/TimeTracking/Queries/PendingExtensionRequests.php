<?php

namespace App\Domain\TimeTracking\Queries;

use App\Domain\TimeTracking\Models\HourExtensionRequest;
use Illuminate\Database\Eloquent\Collection;

/**
 * RF-05.08: the company's pending extension requests, oldest first (web inbox and MCP list_extension_requests).
 */
final class PendingExtensionRequests
{
    /** @return Collection<int, HourExtensionRequest> */
    public function __invoke(int $companyId): Collection
    {
        return HourExtensionRequest::query()->forCompany($companyId)->pending()
            ->whereHas('project') // a deleted project's requests have nothing left to extend
            ->with(['project', 'requester'])->oldest()->get();
    }
}
