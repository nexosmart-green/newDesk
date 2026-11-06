<?php

use App\Domain\Companies\Actions\ProvisionCompany;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * A company provisioned exactly like production, returning its Admin.
 */
function provisionCompany(string $email = 'admin@example.com'): User
{
    return app(ProvisionCompany::class)(
        ['name' => 'NexoSmart'],
        ['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => $email, 'password' => 'password'],
    );
}

function roleNamed(User $admin, string $name): Role
{
    return $admin->company->roles()->where('name', $name)->firstOrFail();
}
