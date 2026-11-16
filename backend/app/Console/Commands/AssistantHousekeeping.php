<?php

namespace App\Console\Commands;

use App\Domain\Assistant\Actions\CleanUpAssistant;
use Illuminate\Console\Command;

/** RF-13: closes stuck turns, expires proposals, purges expired reports and assistant tokens (every 5 minutes). */
class AssistantHousekeeping extends Command
{
    protected $signature = 'assistant:housekeeping';

    protected $description = 'Close stuck assistant turns and purge what expired';

    public function handle(CleanUpAssistant $cleanUp): int
    {
        $done = $cleanUp();
        $this->info("Turnos cerrados: {$done['turns']}, propuestas vencidas: {$done['proposals']}, archivos borrados: {$done['files']}, tokens borrados: {$done['tokens']}.");

        return self::SUCCESS;
    }
}
