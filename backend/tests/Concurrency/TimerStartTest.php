<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\TimeTracking\Actions\AllocateHours;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Facades\Process;

afterEach(fn () => $this->truncateTablesForAllConnections());

/**
 * QA-03 / R-2 with real processes: 8 simultaneous ▶ on the same task end in exactly one running timer.
 * The CACHE_STORE is the database here so the per-user lock is shared across processes like Redis in prod.
 */
it('creates a single timer when the same person presses play from many devices at once', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    $project = app(CreateProject::class)($admin, ['name' => 'BHN', 'code_prefix' => 'bhn', 'type' => 'bag', 'client_id' => $client->id], [$dev->id]);
    app(AllocateHours::class)($admin, $project, 3600, 'Inicial');
    $task = app(CreateTask::class)($admin, $project, ['title' => 'X'], [$dev->id]);

    $script = base_path('tests/Concurrency/Workers/start_timer.php');
    $results = Process::pool(function ($pool) use ($script, $dev, $task) {
        foreach (range(1, 8) as $device) {
            $pool->env(['APP_ENV' => 'testing', 'DB_DATABASE' => 'newdesk_testing', 'CACHE_STORE' => 'database'])
                ->timeout(120)
                ->command([PHP_BINARY, $script, (string) $dev->id, (string) $task->id]);
        }
    })->start()->wait();

    $ids = [];
    foreach ($results->collect() as $result) {
        expect($result->successful())->toBeTrue($result->errorOutput());
        $ids[] = trim($result->output());
    }

    expect(array_unique($ids))->toHaveCount(1)
        ->and(TimeEntry::query()->withoutGlobalScopes()->count())->toBe(1);
});
