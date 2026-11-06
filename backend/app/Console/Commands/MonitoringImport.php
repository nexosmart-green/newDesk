<?php

namespace App\Console\Commands;

use App\Domain\Companies\Models\Company;
use App\Domain\Monitoring\Import\LegacySecretBox;
use App\Domain\Monitoring\Import\MonitoreoImporter;
use App\Domain\StatusPage\Import\StatusPageImporter;
use App\Domain\Support\Import\SupportImporter;
use Illuminate\Console\Command;

/**
 * RF-13.12 / CA-13.23: copies monitoreo's servers and their history, then its clients, users and
 * status pages, then its problem reports with their chat, images and roadmaps into newDesk, reading
 * the `monitoreo_legacy` connection (MONITOREO_DB_*), MONITOREO_ENCRYPTION_KEY and, for the images,
 * monitoreo's STORAGE_DIR. Team users newDesk did not have are created invited and listed; report
 * images missing from STORAGE_DIR are listed, not fatal.
 */
class MonitoringImport extends Command
{
    protected $signature = 'monitoring:import
        {--company= : Company that owns the servers (default: the only one)}
        {--fresh : Empty the monitoring and status page history tables and the reports first}
        {--storage-dir= : monitoreo STORAGE_DIR, to copy the report screenshots and chat images}';

    protected $description = 'Import servers, alerts, site checks, clients, users, status pages and problem reports from the monitoreo database (read-only)';

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
        $reports = new SupportImporter;
        if ($this->option('fresh')) {
            $reports->wipe();
            $statusPages->wipe();
            $importer->wipe();
        }
        $storageDir = $this->option('storage-dir');
        $counts = [...$importer->import($companyId), ...$statusPages->import($companyId), ...$reports->import($companyId, $storageDir)];

        $this->table(['Tabla de monitoreo', 'Filas'], collect($counts)->map(fn (int $rows, string $table) => [$table, $rows])->values()->all());
        if ($statusPages->invited !== []) {
            $this->warn('Usuarios del equipo creados como invitados (sin contraseña):');
            foreach ($statusPages->invited as $email) {
                $this->line("  {$email}");
            }
        }
        if ($storageDir === null) {
            $this->warn('Sin --storage-dir: no se copiaron las capturas ni las imágenes de los reportes.');
        } elseif ($reports->missingFiles !== []) {
            $this->warn('Imágenes de reportes que no están en '.$storageDir.':');
            foreach ($reports->missingFiles as $path) {
                $this->line("  {$path}");
            }
        }

        return self::SUCCESS;
    }
}
