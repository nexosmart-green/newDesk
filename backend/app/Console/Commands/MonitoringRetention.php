<?php

namespace App\Console\Commands;

use App\Domain\Monitoring\Actions\AggregateHourlyMetrics;
use App\Domain\Monitoring\Actions\PurgeExpiredMetrics;
use Illuminate\Console\Command;

/**
 * Hourly (routes/console.php). Re-aggregates the last three complete hours, so a missed run
 * heals itself, then applies retention (monitoreo app.RunRetention).
 */
class MonitoringRetention extends Command
{
    protected $signature = 'monitoring:retention';

    protected $description = 'Aggregate hourly metrics and purge expired metrics and site history';

    public function handle(AggregateHourlyMetrics $aggregate, PurgeExpiredMetrics $purge): int
    {
        $currentHour = now()->utc()->startOfHour()->toImmutable();
        for ($back = 3; $back >= 1; $back--) {
            $aggregate($currentHour->subHours($back));
        }
        foreach ($purge(now()) as $table => $count) {
            $this->line("{$table}: {$count} borradas");
        }

        return self::SUCCESS;
    }
}
