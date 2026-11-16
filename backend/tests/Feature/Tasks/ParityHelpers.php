<?php

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskCategory;
use App\Domain\Tasks\Models\TaskLabel;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Support\Carbon;

require_once __DIR__.'/../Mcp/McpTestHelpers.php';

/** Turns a module off for a role through the real roles endpoint (Configuración → Roles). */
function revokeModule(User $admin, string $roleName, Permission $module): void
{
    $role = roleNamed($admin, $roleName);
    $kept = array_values(array_filter($role->permissionValues(), fn (string $value) => $value !== $module->value));

    test()->actingAs($admin)->putJson("/api/v1/roles/{$role->id}", ['name' => $role->name, 'permissions' => $kept])->assertOk();
}

/**
 * A worked stretch written straight to the ledger (read-side tests). `$seconds` null = running timer.
 */
function logEntry(User $user, Task $task, Carbon $startedAt, ?int $seconds, ?string $comment = 'Trabajo en la tarea', string $source = 'manual'): TimeEntry
{
    return TimeEntry::query()->forCompany($task->company_id)->create([
        'company_id' => $task->company_id,
        'user_id' => $user->id,
        'task_id' => $task->id,
        'project_id' => $task->project_id,
        'started_at' => $startedAt->copy()->utc(),
        'ended_at' => $seconds === null ? null : $startedAt->copy()->utc()->addSeconds($seconds),
        'seconds' => $seconds,
        'source' => $source,
        'comment' => $comment,
        'created_by' => $user->id,
    ]);
}

function categoryFor(User $user, string $name = 'Backend-API'): TaskCategory
{
    return TaskCategory::query()->forCompany($user->company_id)->create(['company_id' => $user->company_id, 'name' => $name]);
}

function labelFor(User $user, string $name = 'Entrega', string $color = '#309798'): TaskLabel
{
    return TaskLabel::query()->forCompany($user->company_id)->create(['company_id' => $user->company_id, 'name' => $name, 'color' => $color]);
}
