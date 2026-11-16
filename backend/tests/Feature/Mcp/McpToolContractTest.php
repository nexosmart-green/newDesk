<?php

use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Tasks\Models\Task;
use App\Domain\TimeTracking\Enums\HourExtensionStatus;
use App\Domain\TimeTracking\Models\HourExtensionRequest;
use App\Mcp\Servers\NewDeskServer;
use App\Mcp\Tools\AddProjectMemberTool;
use App\Mcp\Tools\AllocateHoursTool;
use App\Mcp\Tools\ApproveExtensionRequestTool;
use App\Mcp\Tools\ChangeClientStatusTool;
use App\Mcp\Tools\ChangeProjectStatusTool;
use App\Mcp\Tools\CommentOnTaskTool;
use App\Mcp\Tools\CreateClientTool;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\LogTimeTool;
use App\Mcp\Tools\NewDeskWriteTool;
use App\Mcp\Tools\RejectExtensionRequestTool;
use App\Mcp\Tools\RemoveProjectMemberTool;
use App\Mcp\Tools\StartTimerTool;
use App\Mcp\Tools\UpdateClientTool;
use App\Mcp\Tools\UpdateProjectTool;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

require_once __DIR__.'/McpTestHelpers.php';
require_once __DIR__.'/../Tasks/ParityHelpers.php';

/** @return list<class-string> */
function registeredTools(): array
{
    return (new ReflectionProperty(NewDeskServer::class, 'tools'))->getDefaultValue();
}

it('classifies every registered tool: read-only, or a write that can be prepared before it runs (RF-13.02)', function () {
    foreach (registeredTools() as $tool) {
        $readOnly = (new ReflectionClass($tool))->getAttributes(IsReadOnly::class) !== [];
        $writes = is_subclass_of($tool, NewDeskWriteTool::class);

        expect($readOnly xor $writes)->toBeTrue("{$tool} must be #[IsReadOnly] or extend NewDeskWriteTool");
    }
});

it('prepares a write (validated, authorized, described) without touching the database', function (string $tool, Closure $arguments) {
    $admin = adminUser();
    ['project' => $project, 'task' => $task] = bagProject($admin, [$admin], 10 * 3600);
    $dev = userWithRole($admin);
    $extensionId = $this->actingAs($admin)->postJson("/api/v1/projects/{$project->id}/extension-requests")->json('data.id');
    $clientId = $project->client_id;

    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    $request = new Request($arguments(compact('project', 'task', 'dev', 'extensionId', 'clientId')));
    $prepared = app($tool)->prepare($request, $admin);

    expect($prepared->summary)->not->toBeEmpty()->and($writes)->toBe([]);

    // The listener does see writes: running the prepared write is what changes data.
    $prepared->execute();
    expect($writes)->not->toBe([]);
})->with([
    'create_client' => [CreateClientTool::class, fn () => ['legal_name' => 'Acme SA', 'tax_id' => '30-71234567-8']],
    'update_client' => [UpdateClientTool::class, fn ($c) => ['client_id' => $c['clientId'], 'email' => 'pagos@acme.com']],
    'change_client_status' => [ChangeClientStatusTool::class, fn ($c) => ['client_id' => $c['clientId'], 'status' => 'inactive']],
    'create_project' => [CreateProjectTool::class, fn () => ['name' => 'Web', 'code_prefix' => 'web', 'type' => 'bag']],
    'update_project' => [UpdateProjectTool::class, fn () => ['project' => 'bhn', 'name' => 'BHN 2']],
    'change_project_status' => [ChangeProjectStatusTool::class, fn () => ['project' => 'bhn', 'status' => 'paused']],
    'add_project_member' => [AddProjectMemberTool::class, fn ($c) => ['project' => 'bhn', 'person' => $c['dev']->email]],
    'remove_project_member' => [RemoveProjectMemberTool::class, fn ($c) => ['project' => 'bhn', 'person' => $c['dev']->email]],
    'approve_extension_request' => [ApproveExtensionRequestTool::class, fn ($c) => ['request_id' => $c['extensionId'], 'minutes' => 600, 'reason' => 'Presupuesto #9']],
    'reject_extension_request' => [RejectExtensionRequestTool::class, fn ($c) => ['request_id' => $c['extensionId']]],
    'allocate_hours' => [AllocateHoursTool::class, fn () => ['project' => 'bhn', 'minutes' => 1200, 'reason' => 'Presupuesto #10']],
    'start_timer' => [StartTimerTool::class, fn ($c) => ['code' => $c['task']->code]],
    'log_time' => [LogTimeTool::class, fn ($c) => ['code' => $c['task']->code, 'date' => now()->toDateString(), 'from' => '00:00', 'to' => '00:30', 'comment' => 'Maquetado del header']],
    'comment_task' => [CommentOnTaskTool::class, fn ($c) => ['code' => $c['task']->code, 'body' => 'Listo el header']],
]);

it('describes the change in words a person can confirm', function () {
    $admin = adminUser();
    bagProject($admin, [], 0);

    $prepared = app(AllocateHoursTool::class)
        ->prepare(new Request(['project' => 'bhn', 'minutes' => 1200, 'reason' => 'Presupuesto #123']), $admin);

    expect($prepared->summary)->toBe('Cargar 20h 00m a bhn «BHN» — motivo: Presupuesto #123');
});

it('rejects in the preparation what the action would reject anyway, so nobody confirms a write bound to fail', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $internal] = bagProject($admin, [$dev], 0, 'int', 'internal');
    $token = mcpToken($admin);

    expect(mcpResult(mcpCall($token, 'allocate_hours', ['project' => 'int', 'minutes' => 60, 'reason' => 'Presupuesto #1']))['text'])
        ->toBe('INT es interno: no tiene bolsa de horas.');

    // A request left behind when the project became internal.
    $request = HourExtensionRequest::query()->create([
        'company_id' => $admin->company_id, 'project_id' => $internal->id, 'requested_by' => $dev->id, 'status' => HourExtensionStatus::Pending,
    ]);
    expect(mcpResult(mcpCall($token, 'approve_extension_request', ['request_id' => $request->id, 'minutes' => 60, 'reason' => 'Presupuesto #2']))['text'])
        ->toBe('INT es interno: no tiene bolsa de horas.')
        ->and($request->fresh()->isPending())->toBeTrue();
});

it('needs the time module to create a task and start its timer, like "Crear e iniciar" on the web', function () {
    $admin = adminUser();
    $dev = userWithRole($admin);
    ['project' => $project] = bagProject($admin, [$dev], 3600);
    revokeModule($admin, DefaultRoles::COLLABORATOR, Permission::ModuleTime);

    $result = mcpResult(mcpCall(mcpToken($dev), 'create_task', ['project' => 'bhn', 'title' => 'Nueva', 'start_timer' => true]));

    expect($result['text'])->toStartWith('Tu rol no tiene acceso a: ')
        ->and(Task::query()->where('project_id', $project->id)->where('title', 'Nueva')->exists())->toBeFalse();
});
