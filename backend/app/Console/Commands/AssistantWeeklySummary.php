<?php

namespace App\Console\Commands;

use App\Domain\Assistant\Actions\SendWeeklySummary;
use App\Domain\Companies\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * RF-14.06: runs every hour; each company with the summary on gets last week's summary once its own Monday
 * 9:00 has passed. A server that was down on Monday sends it as soon as it is back; never twice (idempotent).
 */
class AssistantWeeklySummary extends Command
{
    public const LOCAL_DAY_HOUR = 9;

    protected $signature = 'assistant:weekly-summary {--company= : Only this company id} {--force : Ignore the Monday 9:00 wait}';

    protected $description = 'Send last week\'s summary (Discord, mail, web chat) to the companies that have it on';

    public function handle(SendWeeklySummary $send): int
    {
        Company::query()->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))->each(function (Company $company) use ($send) {
            if (! $company->setting('assistant_weekly_summary_enabled') || (! $this->option('force') && ! $this->isDue($company))) {
                return;
            }

            // One company failing must not keep the others without their summary.
            try {
                $summary = $send($company);
            } catch (Throwable $e) {
                report($e);
                $this->error("{$company->name}: no se pudo armar el resumen ({$e->getMessage()}). Se reintenta en la próxima hora.");

                return;
            }
            $this->line($summary === null
                ? "{$company->name}: nada pendiente para el resumen de esta semana."
                : "{$company->name}: resumen ({$summary->written_by}) → ".json_encode($summary->channels).'.');
        });

        return self::SUCCESS;
    }

    private function isDue(Company $company): bool
    {
        $local = now($company->timezone);

        return $local->greaterThanOrEqualTo($local->copy()->startOfWeek(Carbon::MONDAY)->setTime(self::LOCAL_DAY_HOUR, 0));
    }
}
