<?php

namespace App\Domain\Companies\Policies;

use App\Domain\Companies\Models\Company;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;

class CompanyPolicy
{
    public function update(User $user, Company $company): bool
    {
        return $user->company_id === $company->id && $user->hasPermission(Permission::CompanyManage);
    }
}
