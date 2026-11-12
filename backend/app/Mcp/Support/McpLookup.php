<?php

namespace App\Mcp\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Projects\Models\Project;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskChecklistItem;
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
            ?? throw new BusinessRuleViolation("No existe la tarea {$code} o no tenés acceso. Usá list_my_tasks para obtener códigos válidos.", 'not_found', 404);
        Gate::forUser($user)->authorize('view', $task);

        return $task;
    }

    public function project(User $user, string $prefixOrId): Project
    {
        $value = mb_strtolower(trim($prefixOrId));
        $project = Project::query()->forCompany($user->company_id)
            ->where(ctype_digit($value) ? 'id' : 'code_prefix', ctype_digit($value) ? (int) $value : $value)
            ->first()
            ?? throw new BusinessRuleViolation("No existe el proyecto {$prefixOrId} o no tenés acceso. Usá list_projects para obtener prefijos válidos.", 'not_found', 404);
        Gate::forUser($user)->authorize('view', $project);

        return $project;
    }

    /** A subtask of an already authorized task, by number as get_task shows it (1 = first) or by text: exact first, then a single partial match. */
    public function checklistItem(Task $task, string $reference): TaskChecklistItem
    {
        $items = $task->checklistItems()->get()->each->setRelation('task', $task)->values();

        if (ctype_digit($reference) && $items->has((int) $reference - 1)) {
            return $items[(int) $reference - 1];
        }

        $needle = mb_strtolower($reference);
        $exact = $items->first(fn (TaskChecklistItem $item) => mb_strtolower($item->text) === $needle);
        $partial = $items->filter(fn (TaskChecklistItem $item) => str_contains(mb_strtolower($item->text), $needle));

        return $exact ?? ($partial->count() === 1 ? $partial->first() : throw new BusinessRuleViolation(
            $partial->isEmpty()
                ? "No encontré la subtarea «{$reference}» en {$task->code}."
                : "«{$reference}» coincide con varias subtareas de {$task->code}: indicá el número.",
            'checklist_item_unknown',
        ));
    }

    public function status(User $user, string $name): TaskStatus
    {
        $statuses = TaskStatus::query()->forCompany($user->company_id)->orderBy('position')->get();

        return $statuses->first(fn (TaskStatus $status) => mb_strtolower($status->name) === mb_strtolower(trim($name)))
            ?? throw new BusinessRuleViolation(
                "No hay un estado «{$name}». Usá uno de estos: ".$statuses->pluck('name')->implode(', ').'.',
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
            $found = User::query()->where('company_id', $user->company_id)
                ->where(ctype_digit($value) ? 'id' : 'email', ctype_digit($value) ? (int) $value : mb_strtolower($value))
                ->value('id');

            return $found ?? throw new BusinessRuleViolation("No hay nadie con «{$value}» en la empresa. Usá el email o el id de una persona activa.", 'person_unknown');
        }, $people);
    }
}
