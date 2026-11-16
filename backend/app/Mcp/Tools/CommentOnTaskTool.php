<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Actions\CommentOnTask;
use App\Domain\Tasks\Support\TaskLimits;
use App\Mcp\Support\McpLookup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('comment_task')]
#[Description('Comenta una tarea. Para @mencionar (y notificar) pasá los emails de miembros del proyecto en "mentions".')]
class CommentOnTaskTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly CommentOnTask $commentOnTask) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate([
            'code' => 'required|string',
            'body' => 'required|string|max:'.TaskLimits::COMMENT_BODY_MAX,
            'mentions' => 'nullable|array|max:'.TaskLimits::MENTIONS_MAX,
            'mentions.*' => 'string',
        ]);
        $task = $this->lookup->task($user, $input['code']);
        Gate::forUser($user)->authorize('comment', $task);

        $comment = ($this->commentOnTask)($user, $task, trim($input['body']), $this->lookup->userIds($user, $input['mentions'] ?? []));

        return $this->json([
            'commented_on' => $task->code,
            'mentioned' => $comment->mentions->map(fn (User $person) => $person->fullName())->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Código de la tarea, ej. "bhn-60".')->required(),
            'body' => $schema->string()->description('Texto del comentario (markdown).')->required(),
            'mentions' => $schema->array()->items($schema->string())->description('Emails de las personas a mencionar.'),
        ];
    }
}
