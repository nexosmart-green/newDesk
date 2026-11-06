<?php

namespace App\Console\Commands;

use App\Domain\Shared\Activity\ActivityContext;
use App\Domain\TimeTracking\Actions\AutoStopTimers;
use Illuminate\Console\Command;

/**
 * Runs every minute from the scheduler (Supervisord `newdesk-scheduler`), RF-05.06 and RF-05.10.
 */
class CutTimers extends Command
{
    protected $signature = 'timers:cut';

    protected $description = 'Stop timers on exhausted hour bags and forgotten timers';

    public function handle(AutoStopTimers $autoStop, ActivityContext $context): int
    {
        $context->useSystem();
        $now = now();

        $budget = $autoStop->exhaustedBudgets($now);
        $forgotten = $autoStop->forgotten($now);

        if ($budget + $forgotten > 0) {
            $this->info("Cortados: {$budget} sin horas, {$forgotten} olvidados.");
        }

        return self::SUCCESS;
    }
}
