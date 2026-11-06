<?php

namespace App\Console\Commands;

use App\Domain\Companies\Models\Company;
use App\Domain\Monitoring\Import\LegacySecretBox;
use App\Domain\Monitoring\Import\MonitoreoImporter;
use App\Domain\StatusPage\Import\StatusPageImporter;
use Illuminate\Console\Command;

/**
 * RF-13.12 / CA-13.23: copies monitoreo's servers and their history, then its clients, users and
 * status pages into newDesk, reading the `monitoreo_legacy` connection (MONITOREO_DB_*) and
 * MONITOREO_ENCRYPTION_KEY. Team users newDesk did not have are created invited and listed.
 */
class MonitoringImport extends Command
{
    protected $signature = 'monitoring:import
        {--company= : Company that owns the servers (default: the only one)}
        {--fresh : Empty the monitoring and status page history tables first}';

    protected $description = 'Import servers, alerts, site checks, clients, users and status pages from the monitoreo database (read-only)';

    public function handle(): int
    {
        $companies = Company::query()->pluck('id');
        $companyId = $this->option('company') !== null ? (int) $this->option('company') : ($companies->count() === 1 ? $companies->first() : null);
        if ($companyId === null || ! $companies->contains($companyId)) {
            $this->error('Indicá la empresa con --company=<id>.');

            return self::FAILURE;
        }

        $box = new LegacySecretBox((string) config('newdesk.monitoreo.encryption_key'));
        $importer = new MonitoreoImporter($box);
        $statusPages = new StatusPageImporter($box);
        if ($this->option('fresh')) {
            $statusPages->wipe();
            $importer->wipe();
        }
        $counts = [...$importer->import($companyId), ...$statusPages->import($companyId)];

        $this->table(['Tabla de monitoreo', 'Filas'], collect($counts)->map(fn (int $rows, string $table) => [$table, $rows])->values()->all());
        if ($statusPages->invited !== []) {
            $this->warn('Usuarios del equipo creados como invitados (sin contraseña):');
            foreach ($statusPages->invited as $email) {
                $this->line("  {$email}");
            }
        }

        return self::SUCCESS;
    }
}
