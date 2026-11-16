<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Tasks\Actions\CommentOnTask;
use App\Domain\Tasks\Support\TaskLimits;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('comment_task')]
#[Description('Comenta una tarea. Para @mencionar (y notificar) pasá los emails de miembros del proyecto en "mentions".')]
class CommentOnTaskTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleProjects;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly CommentOnTask $commentOnTask) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate([
            'code' => 'required|string',
            'body' => 'required|string|max:'.TaskLimits::COMMENT_BODY_MAX,
            'mentions' => 'nullable|array|max:'.TaskLimits::MENTIONS_MAX,
            'mentions.*' => 'string',
        ]);
        $task = $this->lookup->task($user, $input['code']);
        Gate::forUser($user)->authorize('comment', $task);

        $body = trim($input['body']);
        $mentionIds = $this->lookup->userIds($user, $input['mentions'] ?? []);

        return PreparedWrite::of("Comentar en {$task->code}: «{$body}»", function () use ($user, $task, $body, $mentionIds) {
            $comment = ($this->commentOnTask)($user, $task, $body, $mentionIds);

            return $this->json([
                'commented_on' => $task->code,
                'mentioned' => $comment->mentions->map(fn (User $person) => $person->fullName())->all(),
            ]);
        });
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
