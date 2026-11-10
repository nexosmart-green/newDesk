<?php

namespace App\Mcp\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskStatus;
use Illuminate\Support\Facades\Gate;

/**
 * Resolves the human identifiers a model uses (task code `bhn-60`, project prefix `bhn`, status name,
 * people by e-mail or id) and applies the same visibility policies as the web (404 when hidden).
 */
final class McpLookup
{
    public function task(User $user, string $code): Task
    {
        $task = Task::query()->forCompany($user->company_id)->withCode($code)->first()
            ?? throw new BusinessRuleViolation("No existe la tarea {$code} o no tenés acceso.", 'not_found', 404);
        Gate::forUser($user)->authorize('view', $task);

        return $task;
    }

    public function project(User $user, string $prefixOrId): Project
    {
        $value = mb_strtolower(trim($prefixOrId));
        $project = Project::query()->forCompany($user->company_id)
            ->where(ctype_digit($value) ? 'id' : 'code_prefix', ctype_digit($value) ? (int) $value : $value)
            ->first()
            ?? throw new BusinessRuleViolation("No existe el proyecto {$prefixOrId} o no tenés acceso.", 'not_found', 404);
        Gate::forUser($user)->authorize('view', $project);

        return $project;
    }

    public function status(User $user, string $name): TaskStatus
    {
        $statuses = TaskStatus::query()->forCompany($user->company_id)->orderBy('position')->get();

        return $statuses->first(fn (TaskStatus $status) => mb_strtolower($status->name) === mb_strtolower(trim($name)))
            ?? throw new BusinessRuleViolation(
                "Estado «{$name}» inexistente. Válidos: ".$statuses->pluck('name')->implode(', ').'.',
                'status_unknown',
            );
    }

    /**
     * @param  list<string|int>  $people  e-mails or user ids
     * @return list<int>
     */
    public function userIds(User $user, array $people): array
    {
        return array_map(function (string|int $person) use ($user) {
            $value = trim((string) $person);
            $found = User::query()->where('company_id', $user->company_id)->team()
                ->where(ctype_digit($value) ? 'id' : 'email', ctype_digit($value) ? (int) $value : mb_strtolower($value))
                ->value('id');

            return $found ?? throw new BusinessRuleViolation("No encontré a «{$value}» en la empresa.", 'person_unknown');
        }, $people);
    }
}
