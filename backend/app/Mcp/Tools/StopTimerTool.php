<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Actions\StopTimer;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('stop_timer')]
#[Description('Detiene mi cronómetro activo. "comment" es OBLIGATORIO: qué hice (mínimo 10 caracteres). Sin comentario válido el cronómetro sigue corriendo.')]
class StopTimerTool extends NewDeskTool
{
    public function __construct(private readonly StopTimer $stopTimer) {}

    protected function run(Request $request, User $user): Response
    {
        $entry = ($this->stopTimer)($user, $request->get('comment'));

        return $this->json(['stopped' => McpPresenter::entry($entry)]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['comment' => $schema->string()->description('Qué hice (mínimo 10 caracteres). Se muestra al cliente en el reporte de horas.')->required()];
    }
}
