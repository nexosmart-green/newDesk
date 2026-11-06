<?php

namespace App\Console\Commands;

use App\Domain\Projects\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nightly check of invariants 2 and 3: the cached allocated/consumed seconds must equal the ledgers.
 * A difference means a bug: it is logged to the `errors` channel (/bug-report) and the cache is fixed.
 */
class ReconcileHours extends Command
{
    protected $signature = 'hours:reconcile {--dry-run : Report without fixing}';

    protected $description = 'Compare project hour caches with allocations and time entries';

    public function handle(): int
    {
        $mismatches = 0;

        Project::query()->withoutGlobalScopes()->withTrashed()->orderBy('id')->each(function (Project $project) use (&$mismatches) {
            $consumed = (int) DB::table('time_entries')->where('project_id', $project->id)
                ->whereNotNull('ended_at')->whereNull('deleted_at')->sum('seconds');
            $allocated = (int) DB::table('hour_allocations')->where('project_id', $project->id)->sum('seconds');

            if ($consumed === $project->consumed_seconds && $allocated === $project->allocated_seconds) {
                return;
            }

            $mismatches++;
            Log::channel('errors')->error('hours.reconcile_mismatch', [
                'action' => 'hours:reconcile',
                'project_id' => $project->id,
                'cached' => ['consumed' => $project->consumed_seconds, 'allocated' => $project->allocated_seconds],
                'ledger' => ['consumed' => $consumed, 'allocated' => $allocated],
            ]);

            if (! $this->option('dry-run')) {
                $project->forceFill(['consumed_seconds' => $consumed, 'allocated_seconds' => $allocated])->saveQuietly();
            }
        });

        $this->info($mismatches === 0 ? 'Todo cuadra.' : "{$mismatches} proyecto(s) con diferencias.");

        return $mismatches === 0 ? self::SUCCESS : self::FAILURE;
    }
}
