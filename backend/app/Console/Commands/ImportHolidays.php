<?php

namespace App\Console\Commands;

use App\Domain\Companies\Models\Company;
use App\Domain\Scheduling\Actions\ImportNationalHolidays;
use App\Domain\Scheduling\Exceptions\NationalHolidaysUnavailable;
use Illuminate\Console\Command;

/**
 * P-26: brings a year's national holidays (AR) into every company (or one). Next year by default: the scheduler runs
 * it on December 1st, before anyone reviews that year. Safe to repeat: a day that already has a holiday is left alone.
 */
class ImportHolidays extends Command
{
    protected $signature = 'holidays:import {year? : Año (por defecto, el que viene)} {--company= : Solo esta empresa}';

    protected $description = 'Import the national holidays (AR) of a year from ArgentinaDatos';

    public function handle(ImportNationalHolidays $import): int
    {
        $year = (int) ($this->argument('year') ?? now()->addYear()->year);
        try {
            Company::query()->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
                ->each(function (Company $company) use ($import, $year) {
                    $result = $import($company->id, $year);
                    $this->info("{$company->name}: {$result['added']} feriados agregados de {$year}, {$result['already']} ya estaban.");
                });
        } catch (NationalHolidaysUnavailable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
