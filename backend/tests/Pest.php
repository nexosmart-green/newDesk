<?php

use App\Domain\Companies\Actions\ProvisionCompany;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

// Real parallel processes cannot see uncommitted test transactions: truncate instead.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

const TEST_TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

/**
 * A company provisioned exactly like production, returning its Admin (2FA not yet enabled).
 */
function provisionCompany(string $email = 'admin@example.com'): User
{
    return app(ProvisionCompany::class)(
        ['name' => 'NexoSmart'],
        ['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => $email, 'password' => 'password'],
    );
}

/** Admin ready to use the app: provisioned company + mandatory 2FA enabled. */
function adminUser(string $email = 'admin@example.com'): User
{
    $admin = provisionCompany($email);
    $admin->forceFill([
        'two_factor_secret' => TEST_TOTP_SECRET,
        'two_factor_recovery_codes' => ['recov-ery01'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $admin;
}

function roleNamed(User $admin, string $name): Role
{
    return $admin->company->roles()->where('name', $name)->firstOrFail();
}

function userWithRole(User $admin, string $roleName = DefaultRoles::COLLABORATOR): User
{
    return User::factory()->forRole(roleNamed($admin, $roleName))->create();
}

function currentTotp(string $secret = TEST_TOTP_SECRET): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}
