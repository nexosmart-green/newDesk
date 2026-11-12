<?php

namespace App\Console\Commands;

use App\Domain\Scheduling\Actions\ExtendShiftRecurrences;
use Illuminate\Console\Command;

/**
 * RF-06.03: keeps recurring shifts generated 8 weeks ahead. Safe to run any number of times.
 */
class ExtendRecurringShifts extends Command
{
    protected $signature = 'shifts:extend-recurrences';

    protected $description = 'Generate recurring shifts up to the 8-week horizon';

    public function handle(ExtendShiftRecurrences $extend): int
    {
        $this->info("Turnos generados: {$extend()}");

        return self::SUCCESS;
    }
}
