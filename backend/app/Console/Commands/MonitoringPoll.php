<?php

namespace App\Console\Commands;

use App\Domain\Monitoring\Polling\Poller;
use Illuminate\Console\Command;

/**
 * RF-13.02: the long-running poller, kept alive by supervisor (`supervisor:sync`). It exits
 * by itself after --max-time or past --memory so a fresh process takes over (ADR-0002).
 */
class MonitoringPoll extends Command
{
    protected $signature = 'monitoring:poll
        {--max-time=3600 : Seconds before exiting so supervisor starts a fresh process}
        {--memory=128 : Megabytes of memory before exiting}';

    protected $description = 'Poll every monitor-agent (health, sections, live metrics)';

    public function handle(Poller $poller): int
    {
        $stop = false;
        // Windows dev has no pcntl: there Ctrl+C just kills the process.
        if (extension_loaded('pcntl')) {
            $this->trap([SIGTERM, SIGINT], function () use (&$stop) {
                $stop = true;
            });
        }
        $deadline = time() + (int) $this->option('max-time');
        $memoryLimit = (int) $this->option('memory') * 1024 * 1024;

        $poller->run(fn () => $stop || time() >= $deadline || memory_get_usage(true) >= $memoryLimit);

        return self::SUCCESS;
    }
}
