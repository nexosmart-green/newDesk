<?php

namespace App\Console\Commands;

use App\Domain\Companies\Models\Company;
use App\Domain\Monitoring\Import\LegacySecretBox;
use App\Domain\Monitoring\Import\MonitoreoImporter;
use Illuminate\Console\Command;

/**
 * RF-13.12 / CA-13.23: copies monitoreo's servers and their history into newDesk, reading the
 * `monitoreo_legacy` connection (MONITOREO_DB_*) and MONITOREO_ENCRYPTION_KEY.
 */
class MonitoringImport extends Command
{
    protected $signature = 'monitoring:import
        {--company= : Company that owns the servers (default: the only one)}
        {--fresh : Empty the monitoring tables first}';

    protected $description = 'Import servers, snapshots and metrics from the monitoreo database (read-only)';

    public function handle(): int
    {
        $companies = Company::query()->pluck('id');
        $companyId = $this->option('company') !== null ? (int) $this->option('company') : ($companies->count() === 1 ? $companies->first() : null);
        if ($companyId === null || ! $companies->contains($companyId)) {
            $this->error('Indicá la empresa con --company=<id>.');

            return self::FAILURE;
        }

        $importer = new MonitoreoImporter(new LegacySecretBox((string) config('newdesk.monitoreo.encryption_key')));
        if ($this->option('fresh')) {
            $importer->wipe();
        }
        $counts = $importer->import($companyId);

        $this->table(['Tabla de monitoreo', 'Filas'], collect($counts)->map(fn (int $rows, string $table) => [$table, $rows])->values()->all());

        return self::SUCCESS;
    }
}
