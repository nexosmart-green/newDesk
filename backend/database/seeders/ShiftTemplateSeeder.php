<?php

namespace Database\Seeders;

use App\Domain\Companies\Models\Company;
use App\Domain\Scheduling\Models\ShiftTemplate;
use Illuminate\Database\Seeder;

/**
 * Local development only: the usual shift templates (RF-06.01) so Turnos and Turnos fijos can be tried at once.
 * Idempotent, so it can run alone on an existing dev DB: `php artisan db:seed --class=ShiftTemplateSeeder`.
 */
class ShiftTemplateSeeder extends Seeder
{
    /** name => [starts_at, ends_at, color, mode] */
    private const TEMPLATES = [
        'Completo 9–18' => ['09:00', '18:00', '#309798', 'office'],
        'Mañana 9–13' => ['09:00', '13:00', '#f6beb6', 'hybrid'],
        'Tarde 14–18' => ['14:00', '18:00', '#7c8cf8', 'remote'],
    ];

    public function run(): void
    {
        foreach (Company::query()->pluck('id') as $companyId) {
            foreach (self::TEMPLATES as $name => [$startsAt, $endsAt, $color, $mode]) {
                ShiftTemplate::query()->forCompany($companyId)->firstOrCreate(
                    ['company_id' => $companyId, 'name' => $name],
                    ['starts_at' => $startsAt, 'ends_at' => $endsAt, 'color' => $color, 'mode' => $mode],
                );
            }
        }
    }
}
