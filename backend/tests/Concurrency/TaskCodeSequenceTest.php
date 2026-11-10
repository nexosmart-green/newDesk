<?php

use App\Domain\Clients\Models\Client;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Tasks\Models\Task;
use Illuminate\Support\Facades\Process;

// Data here is really committed: leave the database empty for the transactional suites that follow.
afterEach(fn () => $this->truncateTablesForAllConnections());

/**
 * CA-04.01 / QA-10: real OS processes creating tasks at the same time on the same project.
 */
it('issues correlative task codes with no gaps nor duplicates under concurrency', function () {
    $admin = adminUser();
    $client = Client::factory()->create(['company_id' => $admin->company_id]);
    $project = app(CreateProject::class)($admin, ['name' => 'BH News', 'code_prefix' => 'bhn', 'type' => 'bag', 'client_id' => $client->id]);

    $workers = 6;
    $perWorker = 10;
    $script = base_path('tests/Concurrency/Workers/create_tasks.php');

    $results = Process::pool(function ($pool) use ($workers, $perWorker, $script, $admin, $project) {
        foreach (range(1, $workers) as $worker) {
            $pool->env(['APP_ENV' => 'testing', 'DB_DATABASE' => 'newdesk_testing', 'CACHE_STORE' => 'array'])
                ->timeout(120)
                ->command([PHP_BINARY, $script, (string) $admin->id, (string) $project->id, (string) $perWorker]);
        }
    })->start()->wait();

    foreach ($results->collect() as $result) {
        expect($result->successful())->toBeTrue($result->errorOutput());
    }

    $total = $workers * $perWorker;
    $numbers = Task::query()->forCompany($admin->company_id)->where('project_id', $project->id)->orderBy('number')->pluck('number')->all();

    expect($numbers)->toBe(range(1, $total))
        ->and(Task::query()->forCompany($admin->company_id)->distinct()->count('code'))->toBe($total)
        ->and($project->fresh()->task_sequence)->toBe($total);
});
