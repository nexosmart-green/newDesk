<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Actions\LogManualTime;
use App\Domain\TimeTracking\Support\LocalTimeRange;
use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use App\Mcp\Support\McpLookup;
use App\Mcp\Support\McpPresenter;
use App\Mcp\Support\PreparedWrite;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('log_time')]
#[Description('Carga horas a mano en una tarea asignada a mí (me olvidé el cronómetro). Fecha y horas en MI zona horaria. Solo dentro de la ventana permitida por la empresa; si superan la bolsa se rechazan enteras.')]
class LogTimeTool extends NewDeskWriteTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(private readonly McpLookup $lookup, private readonly LogManualTime $logManualTime) {}

    public function prepare(Request $request, User $user): PreparedWrite
    {
        $input = $request->validate([
            'code' => 'required|string',
            'date' => 'required|date_format:Y-m-d',
            'from' => 'required|date_format:H:i',
            'to' => 'required|date_format:H:i',
            'comment' => 'nullable|string|max:'.TimeTrackingLimits::ENTRY_COMMENT_MAX,
        ]);
        $task = $this->lookup->task($user, $input['code']);
        [$startedAt, $endedAt] = LocalTimeRange::toUtc($user, $input['date'], $input['from'], $input['to']);

        return PreparedWrite::of(
            "Cargar {$input['date']} de {$input['from']} a {$input['to']} en {$task->code} «{$task->title}»",
            fn () => $this->json(['logged' => McpPresenter::entry(($this->logManualTime)($user, $task, $startedAt, $endedAt, $input['comment'] ?? null))]),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('Código de la tarea, ej. "bhn-60".')->required(),
            'date' => $schema->string()->description('YYYY-MM-DD')->required(),
            'from' => $schema->string()->description('HH:MM')->required(),
            'to' => $schema->string()->description('HH:MM')->required(),
            'comment' => $schema->string()->description('Qué hice (mínimo 10 caracteres).')->required(),
        ];
    }
}
