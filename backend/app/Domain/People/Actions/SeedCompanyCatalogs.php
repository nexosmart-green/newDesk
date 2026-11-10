<?php

namespace App\Domain\People\Actions;

use App\Domain\Companies\Models\Company;
use App\Domain\People\Enums\CatalogType;

/**
 * Starting catalog values for a new company. Runs inside ProvisionCompany (production path),
 * never only from a seeder.
 */
final class SeedCompanyCatalogs
{
    public function __invoke(Company $company): void
    {
        foreach (CatalogType::cases() as $type) {
            $model = $type->modelClass();
            foreach ($type->defaults() as $name) {
                $model::query()->forCompany($company->id)->firstOrCreate(['company_id' => $company->id, 'name' => $name]);
            }
        }
    }
}
