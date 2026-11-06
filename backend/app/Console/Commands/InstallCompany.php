<?php

namespace App\Console\Commands;

use App\Domain\Companies\Actions\ProvisionCompany;
use App\Domain\Companies\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Production bootstrap: company + base roles + first Admin (E8 initial load).
 */
class InstallCompany extends Command
{
    protected $signature = 'newdesk:install
        {--company= : Company name shown in titles and emails}
        {--first-name=} {--last-name=} {--email=}
        {--password= : Omit to generate one}';

    protected $description = 'Create the company, its base roles and the first Admin';

    public function handle(ProvisionCompany $provision): int
    {
        if (Company::query()->exists() && ! $this->confirm('Ya existe una empresa. ¿Crear otra?', false)) {
            return self::SUCCESS;
        }

        $password = $this->option('password') ?: Str::password(20);

        $admin = $provision(
            ['name' => $this->option('company') ?: $this->ask('Nombre de la empresa', 'NexoSmart')],
            [
                'first_name' => $this->option('first-name') ?: $this->ask('Nombre del Admin'),
                'last_name' => $this->option('last-name') ?: $this->ask('Apellido del Admin'),
                'email' => $this->option('email') ?: $this->ask('Email del Admin'),
                'password' => $password,
            ],
        );

        $this->info("Admin creado: {$admin->email}");
        if (! $this->option('password')) {
            $this->warn("Contraseña generada (se muestra una sola vez): {$password}");
        }

        return self::SUCCESS;
    }
}
