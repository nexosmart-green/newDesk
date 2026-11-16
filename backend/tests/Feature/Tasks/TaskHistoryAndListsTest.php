<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Models\TaskStatus;
use Carbon\CarbonImmutable;

require_once __DIR__.'/ParityHelpers.php';

it('shows who created the task and when, only in the detail', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    $code = $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Con **markdown**', 'description' => "# Título\n\n- [ ] item"])
        ->assertCreated()->json('data.code');

    $this->actingAs($admin)->getJson("/api/v1/tasks/{$code}")
        ->assertOk()
        ->assertJsonPath('data.created_by', ['id' => $dev->id, 'full_name' => $dev->fullName()])
        ->assertJsonPath('data.created_at', now()->toIso8601String())
        ->assertJsonPath('data.description', "# Título\n\n- [ ] item");

    $this->actingAs($admin)->getJson("/api/v1/projects/{$project->id}/tasks")
        ->assertJsonMissingPath('data.0.created_by')
        ->assertJsonMissingPath('data.0.description');
});

it('records the whole life of a task as its own history, newest first', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $designer = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev, $designer], 3600);
    ['project' => $destination] = bagProject($admin, [$dev, $designer], 3600, 'web');
    $label = labelFor($admin);
    $category = categoryFor($admin);
    $inProgress = TaskStatus::query()->forCompany($admin->company_id)->where('name', 'En curso')->firstOrFail();

    $code = $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Header'])->json('data.code');
    $this->actingAs($dev)->putJson("/api/v1/tasks/{$code}", ['title' => 'Header nuevo'])->assertOk();
    $this->actingAs($dev)->putJson("/api/v1/tasks/{$code}", ['category_id' => $category->id, 'label_ids' => [$label->id]])->assertOk();
    $this->actingAs($dev)->putJson("/api/v1/tasks/{$code}/status", ['status_id' => $inProgress->id])->assertOk();
    $this->actingAs($dev)->putJson("/api/v1/tasks/{$code}/assignees", ['assignee_ids' => [$designer->id]])->assertOk();
    $newCode = $this->actingAs($admin)->putJson("/api/v1/tasks/{$code}/project", ['project_id' => $destination->id])->assertOk()->json('data.task.code');
    // Activity of another task never leaks in.
    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Otra'])->assertCreated();

    $response = $this->actingAs($dev)->getJson("/api/v1/tasks/{$newCode}/activity")->assertOk();

    expect(array_column($response->json('data'), 'event'))->toBe([
        'task.moved_in', 'task.moved_out', 'task.assigned', 'task.status_changed', 'task.updated', 'task.updated', 'task.created',
    ]);
    $response->assertJsonPath('data.6.actor.full_name', $dev->fullName())
        ->assertJsonPath('data.6.source', 'web')
        ->assertJsonPath('data.4.after.labels', ['Entrega'])
        ->assertJsonPath('data.5.before.title', 'Header')
        ->assertJsonPath('data.5.after.title', 'Header nuevo')
        ->assertJsonStructure(['data' => [['id', 'event', 'actor', 'source', 'token_name', 'before', 'after', 'created_at']], 'meta' => ['total']]);
});

it('hides the task history from outsiders (404) and behind the projects module', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $outsider = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);

    $this->actingAs($outsider)->getJson("/api/v1/tasks/{$task->id}/activity")->assertNotFound();
    $this->actingAs(adminUser('otra@example.com'))->getJson("/api/v1/tasks/{$task->id}/activity")->assertNotFound();

    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleProjects);
    $this->actingAs($dev->fresh())->getJson("/api/v1/tasks/{$task->id}/activity")->assertForbidden();
});

it('lists every task of the projects the viewer can see in "Todas las tareas"', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $teammate = userWithRole($admin);
    ['project' => $mine, 'task' => $assignedToMe] = bagProject($admin, [$dev, $teammate], 3600);
    $notMine = app(CreateTask::class)($admin, $mine, ['title' => 'Del compañero'], [$teammate->id]);
    ['task' => $hidden] = bagProject($admin, [$teammate], 3600, 'sec');
    $otherAdmin = adminUser('otra@example.com');
    $foreignProject = app(CreateProject::class)($otherAdmin, ['name' => 'Ajeno', 'code_prefix' => 'aje', 'type' => 'internal'], []);
    app(CreateTask::class)($otherAdmin, $foreignProject, ['title' => 'Ajena']);

    $codes = fn ($user, string $query = '') => collect($this->actingAs($user)->getJson("/api/v1/tasks{$query}")->assertOk()->json('data'))->pluck('code')->sort()->values()->all();

    expect($codes($dev))->toEqual(collect([$assignedToMe->code, $notMine->code])->sort()->values()->all())
        ->and($codes($admin))->toEqual(collect([$assignedToMe->code, $notMine->code, $hidden->code])->sort()->values()->all())
        ->and($codes($dev, "?assignee_id={$teammate->id}"))->toEqual(collect([$assignedToMe->code, $notMine->code])->sort()->values()->all())
        ->and($codes($dev, '?search=compañero'))->toEqual([$notMine->code])
        ->and($codes($admin, "?project_id={$mine->id}&per_page=1"))->toHaveCount(1);

    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleProjects);
    $this->actingAs($dev->fresh())->getJson('/api/v1/tasks')->assertForbidden();
});

it('judges overdue on the viewer local day: at 22:00 in Buenos Aires a task due today is not overdue yet', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $dev->forceFill(['timezone' => 'America/Argentina/Buenos_Aires'])->save();
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    $this->travelTo(CarbonImmutable::parse('2026-11-10 22:00', 'America/Argentina/Buenos_Aires'));
    app(CreateTask::class)($admin, $project, ['title' => 'Hoy', 'due_on' => '2026-11-10'], [$dev->id]);

    $this->actingAs($dev)->getJson('/api/v1/me/tasks')->assertJsonPath('data.0.is_overdue', false);
    $this->actingAs($dev)->getJson('/api/v1/me/tasks?overdue=1')->assertJsonCount(0, 'data');
    $this->actingAs($dev)->getJson('/api/v1/me/dashboard')
        ->assertJsonPath('data.tasks.overdue', 0)
        ->assertJsonPath('data.tasks.due_today', 1);
});
