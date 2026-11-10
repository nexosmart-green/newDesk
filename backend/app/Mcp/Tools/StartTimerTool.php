<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Actions\StartTimer;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('start_timer')]
#[Description('Inicia mi cronómetro en una tarea asignada a mí. Si ya tengo otro corriendo, "comment" es OBLIGATORIO: describe qué hice en el anterior (mínimo 10 caracteres) y se cierra en el mismo paso.')]
class StartTimerTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly StartTimer $startTimer) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate(['code' => 'required|string', 'comment' => 'nullable|string|max:5000']);
        $task = $this->lookup->task($user, $input['code']);

        $entry = ($this->startTimer)($user, $task, $input['comment'] ?? null);

        return $this->json(['running' => McpPresenter::entry($entry)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Código de la tarea, ej. "bhn-60".')->required(),
            'comment' => $schema->string()->description('Qué hice en el cronómetro anterior, si hay uno corriendo (mín. 10 caracteres).'),
        ];
    }
}
