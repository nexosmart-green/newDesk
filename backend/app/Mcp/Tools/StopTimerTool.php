<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Actions\StopTimer;
use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use App\Mcp\Support\McpPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('stop_timer')]
#[Description('Detiene mi cronómetro activo. "comment" es OBLIGATORIO: qué hice (mínimo 10 caracteres). Sin comentario válido el cronómetro sigue corriendo.')]
class StopTimerTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(private readonly StopTimer $stopTimer) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $comment = $request->validate(['comment' => 'nullable|string|max:'.TimeTrackingLimits::ENTRY_COMMENT_MAX])['comment'] ?? null;

        return PreparedWrite::of('Detener mi cronómetro con el comentario: «'.$comment.'»', function () use ($user, $comment) {
            return $this->json(['stopped' => McpPresenter::entry(($this->stopTimer)($user, $comment))]);
        });
    }

    public function schema(JsonSchema $schema): array
    {
        return ['comment' => $schema->string()->description('Qué hice (mínimo 10 caracteres). Se muestra al cliente en el reporte de horas.')->required()];
    }
}
