<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Tasks\Actions\AddChecklistItem;
use App\Domain\Tasks\Actions\UpdateChecklistItem;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskChecklistItem;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_checklist')]
#[Description('Subtareas (checklist) de una tarea: "add" agrega una al final; "check" / "uncheck" la marcan como hecha o pendiente, indicándola por número (como la muestra get_task) o por su texto.')]
class UpdateChecklistTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(
        private readonly McpLookup $lookup,
        private readonly AddChecklistItem $addItem,
        private readonly UpdateChecklistItem $updateItem,
    ) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate([
            'code' => 'required|string',
            'action' => 'required|in:add,check,uncheck',
            'item' => 'required|string|max:'.TaskChecklistItem::MAX_TEXT_LENGTH,
        ]);
        $task = $this->lookup->task($user, $input['code']);
        Gate::forUser($user)->authorize('update', $task);
        $item = trim($input['item']);

        if ($input['action'] === 'add') {
            ($this->addItem)($user, $task, $item);
        } else {
            ($this->updateItem)($user, $this->find($task, $item), done: $input['action'] === 'check');
        }

        return $this->json(['task' => $task->code, 'checklist' => McpPresenter::checklist($task)]);
    }

    /** By number (1 = first) or by text: exact first, then a single partial match. */
    private function find(Task $task, string $reference): TaskChecklistItem
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

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Código de la tarea, ej. "bhn-60".')->required(),
            'action' => $schema->string()->enum(['add', 'check', 'uncheck'])->required(),
            'item' => $schema->string()->description('add: texto de la subtarea nueva. check/uncheck: número de la subtarea (1 = la primera) o su texto.')->required(),
        ];
    }
}
