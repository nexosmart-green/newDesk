<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Actions\AddChecklistItem;
use App\Domain\Tasks\Actions\UpdateChecklistItem;
use App\Domain\Tasks\Models\TaskChecklistItem;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_checklist')]
#[Description('Subtareas (checklist) de una tarea: "add" agrega una al final; "check" / "uncheck" la marcan como hecha o pendiente, indicándola por número (como la muestra get_task) o por su texto.')]
class UpdateChecklistTool extends NewDeskWriteTool
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

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate([
            'code' => 'required|string',
            'action' => 'required|in:add,check,uncheck',
            'item' => 'required|string|max:'.TaskChecklistItem::MAX_TEXT_LENGTH,
        ]);
        $task = $this->lookup->task($user, $input['code']);
        Gate::forUser($user)->authorize('update', $task);
        $text = trim($input['item']);
        $respond = fn () => $this->json(['task' => $task->code, 'checklist' => McpPresenter::checklist($task)]);

        if ($input['action'] === 'add') {
            return PreparedWrite::of("Agregar la subtarea «{$text}» a {$task->code}", function () use ($user, $task, $text, $respond) {
                ($this->addItem)($user, $task, $text);

                return $respond();
            });
        }

        $item = $this->lookup->checklistItem($task, $text);
        $done = $input['action'] === 'check';

        return PreparedWrite::of(($done ? 'Tildar' : 'Destildar')." la subtarea «{$item->text}» de {$task->code}", function () use ($user, $item, $done, $respond) {
            ($this->updateItem)($user, $item, done: $done);

            return $respond();
        });
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
