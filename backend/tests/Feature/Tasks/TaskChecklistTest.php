<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskChecklistItem;

require_once __DIR__.'/ParityHelpers.php';

function addChecklistItem(Task $task, string $text): TaskChecklistItem
{
    $id = test()->postJson("/api/v1/tasks/{$task->code}/checklist", ['text' => $text])->assertCreated()->json('data.id');

    return TaskChecklistItem::query()->findOrFail($id);
}

/** @return list<string> */
function taskEvents(Task $task): array
{
    return ActivityLog::query()->where('subject_type', $task->getMorphClass())->where('subject_id', $task->id)
        ->where('event', 'like', 'checklist.%')->orderBy('id')->pluck('event')->all();
}

it('adds checklist items at the end and shows them ordered in the task detail (RF-04.08)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    $this->actingAs($dev);

    $this->postJson("/api/v1/tasks/{$task->code}/checklist", ['text' => '  Maquetar el header  '])
        ->assertCreated()
        ->assertJsonPath('message', 'Subtarea agregada.')
        ->assertJsonPath('data.text', 'Maquetar el header')
        ->assertJsonPath('data.position', 1)
        ->assertJsonPath('data.done', false)
        ->assertJsonPath('data.done_at', null)
        ->assertJsonPath('data.done_by', null);
    addChecklistItem($task, 'Conectar la API');

    $this->getJson("/api/v1/tasks/{$task->code}")
        ->assertOk()
        ->assertJsonPath('data.checklist.*.text', ['Maquetar el header', 'Conectar la API'])
        ->assertJsonPath('data.checklist.*.position', [1, 2])
        ->assertJsonPath('data.checklist_progress', ['done' => 0, 'total' => 2]);
});

it('records who checked an item and when, clears it when unchecked and renames it', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    $this->actingAs($dev);
    $item = addChecklistItem($task, 'Revisar textos');
    addChecklistItem($task, 'Subir a staging');

    $this->putJson("/api/v1/checklist-items/{$item->id}", ['done' => true])
        ->assertOk()
        ->assertJsonPath('data.done', true)
        ->assertJsonPath('data.done_by', ['id' => $dev->id, 'full_name' => $dev->fullName()]);
    expect($item->fresh()->done_at)->not->toBeNull()->and($item->fresh()->done_by)->toBe($dev->id);
    $this->getJson("/api/v1/tasks/{$task->code}")->assertJsonPath('data.checklist_progress', ['done' => 1, 'total' => 2]);

    $this->putJson("/api/v1/checklist-items/{$item->id}", ['done' => false, 'text' => 'Revisar textos y links'])
        ->assertOk()
        ->assertJsonPath('data.done', false)
        ->assertJsonPath('data.done_by', null)
        ->assertJsonPath('data.text', 'Revisar textos y links');
    expect($item->fresh()->done_at)->toBeNull();

    // Sending what it already is changes nothing in the history.
    $this->putJson("/api/v1/checklist-items/{$item->id}", ['done' => false, 'text' => 'Revisar textos y links'])->assertOk();

    expect(taskEvents($task))->toBe(['checklist.added', 'checklist.added', 'checklist.checked', 'checklist.renamed', 'checklist.unchecked']);
    $renamed = ActivityLog::query()->where('event', 'checklist.renamed')->sole();
    expect($renamed->before)->toBe(['text' => 'Revisar textos'])
        ->and($renamed->after)->toBe(['text' => 'Revisar textos y links'])
        ->and($renamed->project_id)->toBe($task->project_id);
    $this->getJson("/api/v1/tasks/{$task->code}/activity")->assertJsonPath('data.0.event', 'checklist.unchecked');
});

it('deletes an item and keeps its text in the task history', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    $this->actingAs($admin);
    $item = addChecklistItem($task, 'Borrar cache');

    $this->deleteJson("/api/v1/checklist-items/{$item->id}")->assertOk()->assertJsonPath('message', 'Subtarea borrada.');

    expect(TaskChecklistItem::query()->count())->toBe(0)
        ->and(ActivityLog::query()->where('event', 'checklist.removed')->sole()->before)->toBe(['text' => 'Borrar cache']);
    $this->getJson("/api/v1/tasks/{$task->code}")->assertJsonPath('data.checklist', [])->assertJsonPath('data.checklist_progress', ['done' => 0, 'total' => 0]);
});

it('validates the text with clear messages', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    $this->actingAs($admin);
    $item = addChecklistItem($task, 'Algo');

    $this->postJson("/api/v1/tasks/{$task->code}/checklist", ['text' => '   '])
        ->assertUnprocessable()->assertJsonPath('errors.text.0', 'Escribí la subtarea.');
    $this->postJson("/api/v1/tasks/{$task->code}/checklist", ['text' => str_repeat('a', 501)])
        ->assertUnprocessable()->assertJsonPath('errors.text.0', 'La subtarea puede tener hasta 500 caracteres.');
    $this->putJson("/api/v1/checklist-items/{$item->id}", ['text' => ''])
        ->assertUnprocessable()->assertJsonPath('errors.text.0', 'Escribí la subtarea.');
    $this->putJson("/api/v1/checklist-items/{$item->id}", ['done' => 'quizás'])
        ->assertUnprocessable()->assertJsonPath('errors.done.0', 'Indicá si la subtarea está hecha o no.');
});

it('allows at most 100 items per task', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    TaskChecklistItem::query()->insert(array_map(fn (int $n) => [
        'company_id' => $task->company_id, 'task_id' => $task->id, 'text' => "Ítem {$n}", 'position' => $n,
        'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
    ], range(1, 100)));

    $this->actingAs($admin)->postJson("/api/v1/tasks/{$task->code}/checklist", ['text' => 'Uno más'])
        ->assertUnprocessable()
        ->assertJsonPath('data.code', 'checklist_limit')
        ->assertJsonPath('message', 'La tarea ya tiene 100 subtareas, que es el máximo. Borrá alguna o dividí la tarea.');
    expect(TaskChecklistItem::query()->count())->toBe(100);
});

it('reorders the checklist only with exactly the ids of the task', function () {
    $admin = adminUser();
    ['task' => $task] = bagProject($admin, [], 3600);
    ['task' => $other] = bagProject($admin, [], 3600, 'otr');
    $this->actingAs($admin);
    $a = addChecklistItem($task, 'A');
    $b = addChecklistItem($task, 'B');
    $c = addChecklistItem($task, 'C');
    $foreign = addChecklistItem($other, 'X');

    $this->putJson("/api/v1/tasks/{$task->code}/checklist/order", ['ids' => [$c->id, $a->id, $b->id]])
        ->assertOk()
        ->assertJsonPath('data.*.text', ['C', 'A', 'B'])
        ->assertJsonPath('data.*.position', [1, 2, 3]);

    $mismatch = fn (array $ids) => $this->putJson("/api/v1/tasks/{$task->code}/checklist/order", ['ids' => $ids])
        ->assertUnprocessable()->assertJsonPath('data.code', 'checklist_order_mismatch');
    $mismatch([$a->id, $b->id]);
    $mismatch([$a->id, $b->id, $c->id, $foreign->id]);
    $mismatch([$a->id, $b->id, $foreign->id]);
    $this->putJson("/api/v1/tasks/{$task->code}/checklist/order", ['ids' => [$a->id, $a->id, $b->id]])
        ->assertUnprocessable()->assertJsonPath('errors', fn (array $errors) => array_key_exists('ids.1', $errors));
    $this->putJson("/api/v1/tasks/{$task->code}/checklist/order", [])
        ->assertUnprocessable()->assertJsonPath('errors.ids.0', 'Mandá el orden de las subtareas.');

    $this->getJson("/api/v1/tasks/{$task->code}")->assertJsonPath('data.checklist.*.text', ['C', 'A', 'B']);
});

it('hides the checklist from people outside the project or company (404) and honors the projects module', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    $outsider = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    $this->actingAs($dev);
    $item = addChecklistItem($task, 'Privada');

    foreach ([$outsider, adminUser('otra@example.com')] as $stranger) {
        $this->actingAs($stranger);
        $this->postJson("/api/v1/tasks/{$task->id}/checklist", ['text' => ''])->assertNotFound();
        $this->putJson("/api/v1/checklist-items/{$item->id}", ['done' => true])->assertNotFound();
        $this->putJson("/api/v1/checklist-items/{$item->id}", ['text' => str_repeat('a', 600)])->assertNotFound();
        $this->deleteJson("/api/v1/checklist-items/{$item->id}")->assertNotFound();
        $this->putJson("/api/v1/tasks/{$task->id}/checklist/order", ['ids' => [$item->id]])->assertNotFound();
    }
    expect($item->fresh()->done_at)->toBeNull();

    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleProjects);
    $this->actingAs($dev->fresh())->postJson("/api/v1/tasks/{$task->code}/checklist", ['text' => 'Otra'])
        ->assertForbidden()->assertJsonPath('data.code', 'module_disabled');
    $this->actingAs($dev->fresh())->putJson("/api/v1/checklist-items/{$item->id}", ['done' => true])->assertForbidden();
});
