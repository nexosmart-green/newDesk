<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Actions\StartTimer;
use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('start_timer')]
#[Description('Inicia mi cronómetro en una tarea asignada a mí. Si ya tengo otro corriendo, "comment" es OBLIGATORIO: describe qué hice en el anterior (mínimo 10 caracteres) y se cierra en el mismo paso.')]
class StartTimerTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly StartTimer $startTimer) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate(['code' => 'required|string', 'comment' => 'nullable|string|max:'.TimeTrackingLimits::ENTRY_COMMENT_MAX]);
        $task = $this->lookup->task($user, $input['code']);

        return PreparedWrite::of("Iniciar mi cronómetro en {$task->code} «{$task->title}»", function () use ($user, $task, $input) {
            return $this->json(['running' => McpPresenter::entry(($this->startTimer)($user, $task, $input['comment'] ?? null))]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Código de la tarea, ej. "bhn-60".')->required(),
            'comment' => $schema->string()->description('Qué hice en el cronómetro anterior, si hay uno corriendo (mín. 10 caracteres).'),
        ];
    }
}
