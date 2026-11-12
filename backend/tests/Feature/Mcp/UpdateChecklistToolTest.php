<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Tasks\Models\TaskChecklistItem;

require_once __DIR__.'/../Tasks/ParityHelpers.php';

it('adds, checks and unchecks checklist items by number or text, and get_task shows them (RF-04.08)', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    $token = mcpToken($dev);

    mcpCall($token, 'update_checklist', ['code' => $task->code, 'action' => 'add', 'item' => 'Maquetar el header']);
    $added = mcpResult(mcpCall($token, 'update_checklist', ['code' => $task->code, 'action' => 'add', 'item' => 'Conectar la API']));
    expect($added['error'])->toBeFalse()
        ->and($added['json']['checklist'])->toBe([
            ['n' => 1, 'text' => 'Maquetar el header', 'done' => false],
            ['n' => 2, 'text' => 'Conectar la API', 'done' => false],
        ]);

    $checked = mcpResult(mcpCall($token, 'update_checklist', ['code' => $task->code, 'action' => 'check', 'item' => '2']));
    expect($checked['json']['checklist'][1]['done'])->toBeTrue()
        ->and(TaskChecklistItem::query()->withoutGlobalScopes()->where('text', 'Conectar la API')->value('done_by'))->toBe($dev->id);

    mcpCall($token, 'update_checklist', ['code' => $task->code, 'action' => 'check', 'item' => 'header']);
    mcpCall($token, 'update_checklist', ['code' => $task->code, 'action' => 'uncheck', 'item' => 'conectar la api']);

    $detail = mcpResult(mcpCall($token, 'get_task', ['code' => $task->code]));
    expect($detail['json']['checklist'])->toBe([
        ['n' => 1, 'text' => 'Maquetar el header', 'done' => true],
        ['n' => 2, 'text' => 'Conectar la API', 'done' => false],
    ]);
    expect(ActivityLog::query()->withoutGlobalScopes()->where('event', 'like', 'checklist.%')->where('source', 'mcp')->count())->toBe(5);
});

it('explains unknown or ambiguous items, hides other tasks and honors the projects module', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['task' => $task] = bagProject($admin, [$dev], 3600);
    ['task' => $hidden] = bagProject($admin, [], 3600, 'otr');
    $token = mcpToken($dev);
    mcpCall($token, 'update_checklist', ['code' => $task->code, 'action' => 'add', 'item' => 'Revisar textos del home']);
    mcpCall($token, 'update_checklist', ['code' => $task->code, 'action' => 'add', 'item' => 'Revisar textos del footer']);

    $ambiguous = mcpResult(mcpCall($token, 'update_checklist', ['code' => $task->code, 'action' => 'check', 'item' => 'revisar']));
    expect($ambiguous['error'])->toBeTrue()->and($ambiguous['text'])->toContain('coincide con varias subtareas');
    $unknown = mcpResult(mcpCall($token, 'update_checklist', ['code' => $task->code, 'action' => 'check', 'item' => '7']));
    expect($unknown['error'])->toBeTrue()->and($unknown['text'])->toContain('No encontré la subtarea «7»');

    $other = mcpResult(mcpCall($token, 'update_checklist', ['code' => $hidden->code, 'action' => 'add', 'item' => 'Intruso']));
    expect($other['error'])->toBeTrue()->and($other['text'])->toContain('No existe o no tenés acceso');

    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleProjects);
    $disabled = mcpResult(mcpCall($token, 'update_checklist', ['code' => $task->code, 'action' => 'add', 'item' => 'Otra']));
    expect($disabled['error'])->toBeTrue()->and($disabled['text'])->toContain('Tu rol no tiene acceso');
    expect(TaskChecklistItem::query()->withoutGlobalScopes()->count())->toBe(2);
});
