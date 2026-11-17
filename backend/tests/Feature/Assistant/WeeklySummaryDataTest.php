<?php

use App\Domain\Assistant\Queries\WeeklySummaryData;
use App\Domain\Tasks\Actions\CreateTask;
use Illuminate\Support\Carbon;

require_once __DIR__.'/AssistantHelpers.php';
require_once __DIR__.'/../Tasks/ParityHelpers.php';

it('summarizes last week (Monday to Sunday, company time) with the same hours as the web report (CA-14.06)', function () {
    $this->travelTo(Carbon::parse('2026-11-16 12:00', 'America/Argentina/Buenos_Aires')); // a Monday
    $admin = adminUser();
    $ana = userWithRole($admin);
    ['task' => $bhn] = bagProject($admin, [$ana], 10 * 3600, 'bhn');
    ['project' => $idle] = bagProject($admin, [], 10 * 3600, 'idl');
    logEntry($ana, $bhn, Carbon::parse('2026-11-10 10:00', 'America/Argentina/Buenos_Aires'), 2 * 3600);
    logEntry($ana, $bhn, Carbon::parse('2026-11-15 23:30', 'America/Argentina/Buenos_Aires'), 1800);  // Sunday night, local
    logEntry($ana, $bhn, Carbon::parse('2026-11-16 08:00', 'America/Argentina/Buenos_Aires'), 3600);  // this week: out
    logEntry($ana, $bhn, Carbon::parse('2026-11-04 10:00', 'America/Argentina/Buenos_Aires'), 3600);  // the week before

    $summary = app(WeeklySummaryData::class)($admin->company);

    $web = $this->actingAs($admin)->getJson('/api/v1/reports/hours?from=2026-11-09&to=2026-11-15')->json('data.projects');
    expect($summary['week'])->toBe(['from' => '2026-11-09', 'to' => '2026-11-15'])
        ->and($summary['total_seconds'])->toBe(9000)
        ->and($summary['total_seconds'])->toBe(array_sum(array_column($web, 'consumed_in_period_seconds')))
        ->and($summary['previous_total_seconds'])->toBe(3600)
        ->and($summary['by_project'])->toBe([['prefix' => 'bhn', 'name' => 'BHN', 'client' => $bhn->project->client->displayName(), 'seconds' => 9000]])
        ->and($summary['by_person'])->toBe([['name' => $ana->fullName(), 'seconds' => 9000]])
        ->and(array_column($summary['idle_bags'], 'prefix'))->toBe([$idle->code_prefix]);
});

it('lists the bags running out, the overdue tasks and the pending extension requests of the company only', function () {
    $this->travelTo(Carbon::parse('2026-11-16 12:00', 'America/Argentina/Buenos_Aires'));
    $other = adminUser('otra@example.com');
    bagProject($other, [], 3600, 'otr');
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev], 10 * 3600, 'bhn');
    logEntry($dev, $task, Carbon::parse('2026-11-10 10:00', 'America/Argentina/Buenos_Aires'), 9 * 3600);
    $project->forceFill(['consumed_seconds' => 9 * 3600])->save();
    $late = app(CreateTask::class)($admin, $project, ['title' => 'Atrasada', 'due_on' => '2026-11-12'], [$dev->id]);
    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/extension-requests")->assertCreated();
    app('auth')->forgetGuards(); // the scheduled job runs with nobody signed in

    $summary = app(WeeklySummaryData::class)($admin->company);

    expect($summary['bags_running_out'])->toHaveCount(1)
        ->and($summary['bags_running_out'][0])->toMatchArray(['prefix' => 'bhn', 'percent' => 90])
        ->and($summary['overdue']['count'])->toBe(1)
        ->and($summary['overdue']['oldest'][0])->toMatchArray(['code' => $late->code, 'due_on' => '2026-11-12', 'assignees' => [$dev->fullName()]])
        ->and($summary['pending_extensions'])->toHaveCount(1)
        ->and($summary['pending_extensions'][0])->toMatchArray(['project' => 'bhn', 'requested_by' => $dev->fullName()]);

    $empty = app(WeeklySummaryData::class)($other->company);
    expect($empty)->toMatchArray(['total_seconds' => 0, 'by_project' => [], 'by_person' => [], 'bags_running_out' => [], 'pending_extensions' => []])
        ->and($empty['overdue']['count'])->toBe(0);
});
