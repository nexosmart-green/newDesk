<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\Tasks\Models\TaskStatus;

/**
 * Base statuses for a new company (RF-04.03). Runs inside ProvisionCompany.
 */
final class SeedTaskStatuses
{
    public function __invoke(Company $company): void
    {
        foreach (TaskStatus::DEFAULTS as $position => $status) {
            TaskStatus::query()->forCompany($company->id)->firstOrCreate(
                ['company_id' => $company->id, 'name' => $status['name']],
                [...$status, 'position' => $position + 1],
            );
        }
    }
}
