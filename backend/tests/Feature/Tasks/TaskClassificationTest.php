<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Tasks\Models\Task;
use Database\Seeders\TaskCatalogSeeder;

require_once __DIR__.'/ParityHelpers.php';

it('lets anyone who creates tasks add a category inline, unique per company', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $otherAdmin = adminUser('otra@example.com');
    categoryFor($otherAdmin, 'Backend-API'); // same name in another company is fine

    $this->actingAs($dev)->postJson('/api/v1/task-categories', ['name' => '  Backend-API '])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Backend-API')
        ->assertJsonPath('data.tasks_count', 0);

    $this->actingAs($dev)->postJson('/api/v1/task-categories', ['name' => 'Backend-API'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Ya existe la categoría Backend-API.');
    $this->actingAs($dev)->postJson('/api/v1/task-categories', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Poné un nombre para la categoría.');

    expect(ActivityLog::query()->where('event', 'task_category.created')->count())->toBe(1);
});

it('lists categories by name with how many tasks use them', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    $ticket = categoryFor($admin, 'Ticket');
    categoryFor($admin, 'General');
    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Bug', 'category_id' => $ticket->id])->assertCreated();

    $this->actingAs($dev)->getJson('/api/v1/task-categories')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'General')
        ->assertJsonPath('data.0.tasks_count', 0)
        ->assertJsonPath('data.1.name', 'Ticket')
        ->assertJsonPath('data.1.tasks_count', 1);
});

it('renames and deletes categories only with projects.manage; deleting leaves tasks without category', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    $category = categoryFor($admin, 'QA');
    $taskId = $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Probar', 'category_id' => $category->id])->json('data.id');

    $this->actingAs($dev)->putJson("/api/v1/task-categories/{$category->id}", ['name' => 'Tarea QA'])
        ->assertForbidden()->assertJsonPath('message', 'Solo quien gestiona proyectos puede editar o borrar categorías.');
    $this->actingAs($dev)->deleteJson("/api/v1/task-categories/{$category->id}")->assertForbidden();

    $this->actingAs($admin)->putJson("/api/v1/task-categories/{$category->id}", ['name' => 'Tarea QA'])
        ->assertOk()->assertJsonPath('data.name', 'Tarea QA')->assertJsonPath('data.tasks_count', 1);
    $this->actingAs($admin)->deleteJson("/api/v1/task-categories/{$category->id}")->assertOk();

    expect(Task::query()->withoutGlobalScopes()->find($taskId)->category_id)->toBeNull();
    $this->actingAs($dev)->getJson("/api/v1/tasks/{$taskId}")->assertJsonPath('data.category', null);
});

it('never exposes or edits another company catalog (404)', function () {
    $admin = adminUser();
    $otherAdmin = adminUser('otra@example.com');
    $theirs = categoryFor($otherAdmin, 'Secreta');
    $theirLabel = labelFor($otherAdmin, 'Secreta');

    $this->actingAs($admin)->getJson('/api/v1/task-categories')->assertJsonCount(0, 'data');
    $this->actingAs($admin)->putJson("/api/v1/task-categories/{$theirs->id}", ['name' => 'X'])->assertNotFound();
    $this->actingAs($admin)->deleteJson("/api/v1/task-labels/{$theirLabel->id}")->assertNotFound();
});

it('manages labels with a #rrggbb color; deleting one removes it from its tasks', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);

    $labelId = $this->actingAs($dev)->postJson('/api/v1/task-labels', ['name' => 'Entrega', 'color' => '#30979A'])
        ->assertCreated()->assertJsonPath('data.color', '#30979a')->json('data.id');
    $this->actingAs($dev)->postJson('/api/v1/task-labels', ['name' => 'Entrega', 'color' => '#309798'])
        ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Ya existe la etiqueta Entrega.');
    $this->actingAs($dev)->postJson('/api/v1/task-labels', ['name' => 'Rosa', 'color' => 'pink'])
        ->assertUnprocessable()->assertJsonPath('errors.color.0', 'El color tiene que tener el formato #rrggbb.');

    $taskId = $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Release', 'label_ids' => [$labelId]])
        ->assertCreated()->json('data.id');

    $this->actingAs($dev)->putJson("/api/v1/task-labels/{$labelId}", ['name' => 'Entregable', 'color' => '#000000'])->assertForbidden();
    $this->actingAs($admin)->putJson("/api/v1/task-labels/{$labelId}", ['name' => 'Entregable', 'color' => '#f6beb6'])
        ->assertOk()->assertJsonPath('data.name', 'Entregable')->assertJsonPath('data.tasks_count', 1);
    $this->actingAs($dev)->getJson('/api/v1/task-labels')->assertJsonPath('data.0.tasks_count', 1);

    $this->actingAs($admin)->deleteJson("/api/v1/task-labels/{$labelId}")->assertOk();
    $this->actingAs($dev)->getJson("/api/v1/tasks/{$taskId}")->assertJsonPath('data.labels', []);
});

it('saves category and labels on tasks, rejects other companies values and logs the change', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project, 'task' => $task] = bagProject($admin, [$dev], 3600);
    $category = categoryFor($admin, 'Backend-API');
    $delivery = labelFor($admin, 'Entrega', '#309798');
    $urgent = labelFor($admin, 'Alta prioridad', '#f6beb6');
    $otherAdmin = adminUser('otra@example.com');
    $foreignLabel = labelFor($otherAdmin, 'Ajena');
    $foreignCategory = categoryFor($otherAdmin, 'Ajena');

    $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", [
        'title' => 'API', 'category_id' => $category->id, 'label_ids' => [$delivery->id, $urgent->id],
    ])->assertCreated()
        ->assertJsonPath('data.category', ['id' => $category->id, 'name' => 'Backend-API'])
        ->assertJsonPath('data.labels.0', ['id' => $urgent->id, 'name' => 'Alta prioridad', 'color' => '#f6beb6'])
        ->assertJsonPath('data.labels.1.name', 'Entrega');

    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->id}", ['label_ids' => [$foreignLabel->id]])
        ->assertUnprocessable()->assertJsonPath('errors', ['label_ids.0' => ['Alguna de las etiquetas elegidas no existe.']]);
    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->id}", ['category_id' => $foreignCategory->id])
        ->assertUnprocessable()->assertJsonPath('errors.category_id.0', 'La categoría elegida no existe.');

    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->id}", ['category_id' => $category->id, 'label_ids' => [$delivery->id]])
        ->assertOk()->assertJsonPath('data.category.name', 'Backend-API')->assertJsonCount(1, 'data.labels');

    $log = ActivityLog::query()->where('subject_id', $task->id)->where('event', 'task.updated')->latest('id')->first();
    expect($log->before)->toMatchArray(['category' => null, 'labels' => []])
        ->and($log->after)->toMatchArray(['category' => 'Backend-API', 'labels' => ['Entrega']]);

    // Labels only: still an audited change; the same set again is not.
    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->id}", ['label_ids' => []])->assertOk()->assertJsonPath('data.labels', []);
    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->id}", ['label_ids' => []])->assertOk();
    expect(ActivityLog::query()->where('subject_id', $task->id)->where('event', 'task.updated')->count())->toBe(2);

    $this->actingAs($dev)->putJson("/api/v1/tasks/{$task->id}", ['category_id' => null])->assertOk()->assertJsonPath('data.category', null);
});

it('filters every task list by category and label', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    $ticket = categoryFor($admin, 'Ticket');
    $delivery = labelFor($admin);
    $create = fn (array $data) => $this->actingAs($dev)->postJson("/api/v1/projects/{$project->id}/tasks", [...$data, 'assignee_ids' => [$dev->id]])->json('data.code');
    $both = $create(['title' => 'Ambas', 'category_id' => $ticket->id, 'label_ids' => [$delivery->id]]);
    $onlyCategory = $create(['title' => 'Solo categoría', 'category_id' => $ticket->id]);
    $create(['title' => 'Nada']);

    foreach (['/api/v1/tasks', '/api/v1/me/tasks', "/api/v1/projects/{$project->id}/tasks"] as $url) {
        $codes = fn (string $query) => collect($this->actingAs($dev)->getJson("{$url}?{$query}")->assertOk()->json('data'))->pluck('code')->sort()->values()->all();

        expect($codes("category_id={$ticket->id}"))->toEqual(collect([$both, $onlyCategory])->sort()->values()->all())
            ->and($codes("label_id={$delivery->id}"))->toEqual([$both])
            ->and($codes("category_id={$ticket->id}&label_id={$delivery->id}"))->toEqual([$both]);
    }
});

it('keeps catalogs behind the projects module', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleProjects);

    $this->actingAs($dev->fresh())->getJson('/api/v1/task-categories')->assertForbidden()->assertJsonPath('data.code', 'module_disabled');
    $this->actingAs($dev->fresh())->postJson('/api/v1/task-labels', ['name' => 'X', 'color' => '#000000'])->assertForbidden();
});

it('seeds the desk catalogs for local development without duplicating them', function () {
    $admin = adminUser();

    $this->seed(TaskCatalogSeeder::class);
    $this->seed(TaskCatalogSeeder::class);

    $this->actingAs($admin)->getJson('/api/v1/task-categories')->assertJsonCount(6, 'data');
    $this->actingAs($admin)->getJson('/api/v1/task-labels')->assertJsonFragment(['name' => 'Alta prioridad', 'color' => '#f6beb6']);
});
