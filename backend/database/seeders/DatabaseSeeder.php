<?php

namespace Database\Seeders;

use App\Domain\Companies\Actions\ProvisionCompany;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local development data only. Production uses `php artisan newdesk:install`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(ProvisionCompany $provision): void
    {
        $admin = $provision(
            ['name' => 'NexoSmart'],
            ['first_name' => 'Admin', 'last_name' => 'NexoSmart', 'email' => 'admin@nexosmart.com', 'password' => 'password'],
        );

        $roles = $admin->company->roles()->get()->keyBy('name');

        User::factory()->forRole($roles[DefaultRoles::MANAGER])->create([
            'first_name' => 'Gina', 'last_name' => 'Gestora', 'email' => 'gestor@nexosmart.com',
        ]);
        User::factory()->forRole($roles[DefaultRoles::COLLABORATOR])->create([
            'first_name' => 'Juan', 'last_name' => 'Colaborador', 'email' => 'colaborador@nexosmart.com',
        ]);
    }
}
