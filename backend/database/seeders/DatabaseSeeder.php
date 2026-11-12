<?php

namespace Database\Seeders;

use App\Domain\Clients\Actions\SaveClient;
use App\Domain\Companies\Actions\ProvisionCompany;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\TimeTracking\Actions\AllocateHours;
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
        $collaborator = User::factory()->forRole($roles[DefaultRoles::COLLABORATOR])->create([
            'first_name' => 'Juan', 'last_name' => 'Colaborador', 'email' => 'colaborador@nexosmart.com',
        ]);

        $this->call(TaskCatalogSeeder::class);
        $this->call(ShiftTemplateSeeder::class);
        $this->call(ShiftCoordinatorSeeder::class);
        $this->sampleProject($admin, $collaborator);
    }

    /** A bag project with hours and a task assigned to the collaborator (used by the E2E timer flow). */
    private function sampleProject(User $admin, User $collaborator): void
    {
        $client = app(SaveClient::class)($admin, ['legal_name' => 'BH News S.A.', 'trade_name' => 'bhnews']);
        $project = app(CreateProject::class)($admin, [
            'name' => 'BH News', 'code_prefix' => 'bhn', 'type' => 'bag', 'client_id' => $client->id, 'leader_user_id' => $collaborator->id,
        ], [$collaborator->id]);
        app(AllocateHours::class)($admin, $project, 40 * 3600, 'Bolsa inicial', 'Presupuesto #1');
        app(CreateTask::class)($admin, $project, ['title' => 'Maquetar el header'], [$collaborator->id]);
    }
}
