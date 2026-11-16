<?php

use App\Domain\Tasks\Actions\UpdateTask;

require_once __DIR__.'/../Tasks/ParityHelpers.php';

it('shows logged hours, category and labels of tasks in MCP', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 100 * 3600);
    $category = categoryFor($admin, 'Backend-API');
    $label = labelFor($admin, 'Entrega');
    app(UpdateTask::class)($admin, $task, ['category_id' => $category->id, 'label_ids' => [$label->id]]);
    logEntry($dev, $task, now()->subHours(2), 3600);
    logEntry($dev, $task, now()->subMinutes(30), null);
    $token = mcpToken($dev);

    $task = mcpResult(mcpCall($token, 'get_task', ['code' => $task->code]))['json'];
    expect($task)->toMatchArray(['logged_seconds' => 5400, 'logged' => '1h 30m', 'category' => 'Backend-API', 'labels' => ['Entrega']]);

    $list = mcpResult(mcpCall($token, 'list_my_tasks'))['json'];
    expect($list['tasks'][0])->toMatchArray(['logged_seconds' => 5400, 'category' => 'Backend-API', 'labels' => ['Entrega']]);
});
