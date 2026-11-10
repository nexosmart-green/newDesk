<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Actions\CompletePendingComment;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('complete_pending_comment')]
#[Description('Completa el comentario de un registro que el sistema cortó automáticamente (sin horas, olvidado, tarea cerrada…). Mientras haya alguno pendiente no se puede iniciar otro cronómetro. Los ids salen de whoami.')]
class CompletePendingCommentTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(private readonly CompletePendingComment $complete) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate(['time_entry_id' => 'required|integer', 'comment' => 'nullable|string|max:5000']);
        $entry = TimeEntry::query()->forCompany($user->company_id)->where('user_id', $user->id)->findOrFail($input['time_entry_id']);

        return $this->json(['completed' => McpPresenter::entry(($this->complete)($user, $entry, $input['comment'] ?? null))]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'time_entry_id' => $schema->integer()->required(),
            'comment' => $schema->string()->description('Qué hice (mínimo 10 caracteres).')->required(),
        ];
    }
}
