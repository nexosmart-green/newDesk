<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Models\TaskComment;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_task')]
#[Description('Detalle de una tarea por código (ej. "bhn-60"), con descripción, subtareas (checklist) y comentarios.')]
#[IsReadOnly]
class GetTask extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly McpLookup $lookup) {}

    protected function run(Request $request, User $user): Response
    {
        $request->validate(['code' => 'required|string']);
        $task = $this->lookup->task($user, $request->get('code'));

        return $this->json([
            ...McpPresenter::task($task),
            'description' => $task->description,
            'checklist' => McpPresenter::checklist($task),
            'comments' => $task->comments()->with('author')->oldest('id')->limit(100)->get()
                ->map(fn (TaskComment $comment) => [
                    'author' => $comment->author->fullName(),
                    'body' => $comment->body,
                    'at' => $comment->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['code' => $schema->string()->description('Código de la tarea, ej. "bhn-60".')->required()];
    }
}
