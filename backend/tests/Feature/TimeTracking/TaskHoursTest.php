<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Tasks\Actions\CreateTask;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Tasks/ParityHelpers.php';

it('reports logged_seconds per task in detail, project list and Mis tareas, running timers up to now', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $designer = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev, $designer], 100 * 3600);

    logEntry($dev, $task, now()->subHours(3), 3600);
    logEntry($designer, $task, now()->subMinutes(30), null); // running: 1800 s so far
    logEntry($dev, $task, now()->subHours(5), 999)->delete(); // deleted entries never count

    $this->actingAs($dev)->getJson("/api/v1/tasks/{$task->code}")->assertOk()->assertJsonPath('data.logged_seconds', 5400);
    $this->actingAs($dev)->getJson("/api/v1/projects/{$project->id}/tasks")->assertJsonPath('data.0.logged_seconds', 5400);
    $this->actingAs($dev)->getJson('/api/v1/me/tasks')->assertJsonPath('data.0.logged_seconds', 5400);

    $this->travel(60)->seconds();
    $this->actingAs($dev)->getJson('/api/v1/me/tasks')->assertJsonPath('data.0.logged_seconds', 5460);
});

it('reports 0 logged_seconds for a task without hours', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);

    $this->actingAs($dev)->getJson("/api/v1/tasks/{$task->code}")->assertJsonPath('data.logged_seconds', 0);
});

it('lists tasks without one query per row (logged_seconds, category, labels)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev], 100 * 3600);
    $label = labelFor($admin);
    $category = categoryFor($admin);

    $countQueries = function () use ($dev, $project) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($dev)->getJson("/api/v1/projects/{$project->id}/tasks")->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->id}", ['category_id' => $category->id, 'label_ids' => [$label->id]])->assertOk();
    logEntry($dev, $task, now()->subHour(), 600);
    $withOne = $countQueries();

    foreach (range(1, 5) as $n) {
        $more = app(CreateTask::class)($admin, $project, ['title' => "Extra {$n}", 'category_id' => $category->id, 'label_ids' => [$label->id]], [$dev->id]);
        logEntry($dev, $more, now()->subHours(2 + $n), 300);
    }

    expect($countQueries())->toBe($withOne);
});

it('lists the hours of a task, newest first, with the total and never rates', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $designer = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev, $designer], 100 * 3600);

    logEntry($dev, $task, now()->subDays(2), 3600, 'Maquetado del header');
    logEntry($designer, $task, now()->subMinutes(10), null, null, 'timer');

    $this->actingAs($dev)->getJson("/api/v1/tasks/{$task->code}/time-entries")
        ->assertOk()
        ->assertJsonPath('meta.total_seconds', 4200)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.user.id', $designer->id)
        ->assertJsonPath('data.0.is_running', true)
        ->assertJsonPath('data.0.seconds', 600)
        ->assertJsonPath('data.0.source', 'timer')
        ->assertJsonPath('data.1.user.full_name', $dev->fullName())
        ->assertJsonPath('data.1.comment', 'Maquetado del header')
        ->assertJsonPath('data.1.ended_at', now()->subDays(2)->addHour()->toIso8601String())
        ->assertJsonStructure(['data' => [['id', 'user' => ['id', 'full_name', 'avatar'], 'started_at', 'ended_at', 'seconds', 'is_running', 'comment', 'comment_pending', 'source']]])
        ->assertJsonMissingPath('data.0.rate')
        ->assertJsonMissingPath('data.0.amount');
});

it('hides the hours of a task from outsiders and other companies (404) and honors the time module', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $outsider = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    $otherAdmin = adminUser('otra@example.com');

    $this->actingAs($outsider)->getJson("/api/v1/tasks/{$task->id}/time-entries")->assertNotFound();
    $this->actingAs($otherAdmin)->getJson("/api/v1/tasks/{$task->id}/time-entries")->assertNotFound();

    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleTime);
    $this->actingAs($dev->fresh())->getJson("/api/v1/tasks/{$task->id}/time-entries")
        ->assertForbidden()->assertJsonPath('data.code', 'module_disabled');
});
