<?php

require_once __DIR__.'/../TimeTracking/TimeTrackingHelpers.php';

it('reports hours per project and period with sold, consumed, % and per person (RF-10.01)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task, 'project' => $project] = bagProject($admin, [$dev], 4 * 3600);
    startTimer($dev, $task);
    $this->travel(1)->hours();
    stopTimer($dev);

    $this->actingAs($admin)->getJson('/api/v1/reports/hours')
        ->assertOk()
        ->assertJsonPath('data.projects.0.allocated_seconds', 14400)
        ->assertJsonPath('data.projects.0.consumed_in_period_seconds', 3600)
        ->assertJsonPath('data.projects.0.percent_consumed', 25);

    $this->actingAs($admin)->getJson("/api/v1/reports/hours/projects/{$project->id}")
        ->assertOk()->assertJsonPath('data.by_person.0.seconds', 3600)->assertJsonPath('data.by_task.0.code', 'bhn-1');

    $this->actingAs($admin)->getJson('/api/v1/reports/hours?from=2000-01-01&to=2000-01-31')
        ->assertJsonPath('data.projects.0.consumed_in_period_seconds', 0);
});

it('is only for projects.manage', function () {
    $this->actingAs(userWithRole(adminUser()))->getJson('/api/v1/reports/hours')->assertForbidden();
});
