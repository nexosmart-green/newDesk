<?php

namespace Database\Seeders;

use App\Domain\Companies\Models\Company;
use App\Domain\Tasks\Models\TaskCategory;
use App\Domain\Tasks\Models\TaskLabel;
use Illuminate\Database\Seeder;

/**
 * Local development only: desk's task categories and a couple of labels for every company.
 * Idempotent, so it can run alone on an existing dev DB: `php artisan db:seed --class=TaskCatalogSeeder`.
 */
class TaskCatalogSeeder extends Seeder
{
    private const CATEGORIES = ['General', 'Front-Diseño', 'Backend-API', 'Tarea QA', 'Diseño', 'Ticket'];

    private const LABELS = ['Alta prioridad' => '#f6beb6', 'Entrega' => '#309798', 'Bloqueante' => '#ff6b6b'];

    public function run(): void
    {
        foreach (Company::query()->pluck('id') as $companyId) {
            foreach (self::CATEGORIES as $name) {
                TaskCategory::query()->forCompany($companyId)->firstOrCreate(['company_id' => $companyId, 'name' => $name]);
            }
            foreach (self::LABELS as $name => $color) {
                TaskLabel::query()->forCompany($companyId)->firstOrCreate(['company_id' => $companyId, 'name' => $name], ['color' => $color]);
            }
        }
    }
}
