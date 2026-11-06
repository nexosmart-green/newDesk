<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\TimeTracking\Models\TimeEntry;

require_once __DIR__.'/../Mcp/McpTestHelpers.php';

/** bhn (with $originSeconds loaded) and otr (with $destinationSeconds), dev member of both, 1 h logged on bhn-1. */
function twoProjectsWithLoggedHour(int $destinationSeconds = 10 * 3600, ?array $destinationMembers = null): array
{
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task, 'project' => $from] = bagProject($admin, [$dev], 10 * 3600, 'bhn');
    ['project' => $to] = bagProject($admin, $destinationMembers ?? [$dev], $destinationSeconds, 'otr');
    startTimer($dev, $task);
    test()->travel(1)->hours();
    stopTimer($dev);

    return compact('admin', 'dev', 'task', 'from', 'to');
}

it('moves a task with its hours: new code, both bags updated, old code still works (CA-04.12a)', function () {
    ['admin' => $admin, 'task' => $task, 'from' => $from, 'to' => $to] = twoProjectsWithLoggedHour();

    $this->actingAs($admin)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $to->id])
        ->assertOk()
        ->assertJsonPath('data.task.code', 'otr-2')
        ->assertJsonPath('data.seconds_moved', 3600);

    expect($from->fresh()->consumed_seconds)->toBe(0)
        ->and($to->fresh()->consumed_seconds)->toBe(3600)
        ->and(TimeEntry::query()->first()->project_id)->toBe($to->id);

    $this->actingAs($admin)->getJson('/api/v1/tasks/bhn-1')->assertOk()->assertJsonPath('data.code', 'otr-2');
    expect(ActivityLog::query()->whereIn('event', ['task.moved_out', 'task.moved_in'])->pluck('project_id')->sort()->values()->all())
        ->toBe([$from->id, $to->id]);
    $this->artisan('hours:reconcile')->assertSuccessful();
});

it('rejects the whole move when the destination bag cannot take the hours (CA-04.12b)', function () {
    ['admin' => $admin, 'task' => $task, 'from' => $from, 'to' => $to] = twoProjectsWithLoggedHour(1800);

    $this->actingAs($admin)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $to->id])
        ->assertUnprocessable()->assertJsonPath('data.code', 'move_exceeds_bag');

    expect($task->fresh()->code)->toBe('bhn-1')->and($from->fresh()->consumed_seconds)->toBe(3600);
});

it('refuses with a running timer or into a closed project (CA-04.12c)', function () {
    ['admin' => $admin, 'dev' => $dev, 'task' => $task, 'to' => $to] = twoProjectsWithLoggedHour();
    startTimer($dev, $task);

    $this->actingAs($admin)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $to->id])
        ->assertUnprocessable()->assertJsonPath('data.code', 'task_timer_running');

    stopTimer($dev);
    $this->actingAs($admin)->putJson("/api/v1/projects/{$to->id}/status", ['status' => 'finished']);
    $this->actingAs($admin)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $to->id])
        ->assertUnprocessable()->assertJsonPath('data.code', 'project_closed');
});

it('needs hours.allocate to move a task with hours; without hours any member of both can (CA-04.12d)', function () {
    ['admin' => $admin, 'dev' => $dev, 'task' => $task, 'from' => $from, 'to' => $to] = twoProjectsWithLoggedHour();

    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $to->id])->assertForbidden();

    $fresh = $this->actingAs($dev)->postJson("/api/v1/projects/{$from->id}/tasks", ['title' => 'Sin horas'])->json('data.code');
    $this->actingAs($dev)->putJson("/api/v1/tasks/{$fresh}/project", ['project_id' => $to->id])->assertOk();

    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    $this->actingAs($manager)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $to->id])->assertOk();
});

it('drops assignees who are not members of the destination, keeping their hours', function () {
    ['admin' => $admin, 'task' => $task, 'to' => $to] = twoProjectsWithLoggedHour(10 * 3600, []);

    $this->actingAs($admin)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $to->id])
        ->assertOk()->assertJsonCount(1, 'data.unassigned')->assertJsonPath('data.task.assignees', []);
    expect(TimeEntry::query()->sum('seconds'))->toEqual(3600);
});

it('hides projects the person cannot see (404) and works through MCP too', function () {
    ['admin' => $admin, 'dev' => $dev, 'task' => $task] = twoProjectsWithLoggedHour();
    ['project' => $secret] = bagProject($admin, [], 3600, 'sec');

    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->code}/project", ['project_id' => $secret->id])->assertNotFound();

    $result = mcpResult(mcpCall(mcpToken($admin), 'move_task', ['code' => 'bhn-1', 'project' => 'otr']));
    expect($result['error'])->toBeFalse()->and($result['json']['moved']['code'])->toBe('otr-2')->and($result['json']['hours_moved'])->toBe('1h 00m');
});
