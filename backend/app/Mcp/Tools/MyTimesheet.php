<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Models\User;
use App\Domain\TimeTracking\Queries\Timesheet;
use App\Mcp\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('my_timesheet')]
#[Description('Mis horas por día entre dos fechas (máx. 62 días), con el comentario de cada registro.')]
#[IsReadOnly]
class MyTimesheet extends NewDeskTool
{
    public function __construct(private readonly Timesheet $timesheet) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        if (now()->parse($input['from'])->diffInDays(now()->parse($input['to'])) > 62) {
            return Response::error('El rango máximo es de 62 días.');
        }

        $sheet = ($this->timesheet)($user, $input['from'], $input['to']);

        return $this->json([
            'timezone' => $sheet['timezone'],
            'total' => McpPresenter::hours($sheet['total_seconds']),
            'days' => array_map(fn (array $day) => [
                'date' => $day['date'],
                'total' => McpPresenter::hours($day['total_seconds']),
                'entries' => array_map(fn (array $entry) => [
                    'task' => $entry['task']['code'],
                    'time' => McpPresenter::hours($entry['seconds']),
                    'comment' => $entry['comment'],
                    'comment_pending' => $entry['comment_pending'],
                ], $day['entries']),
            ], array_values(array_filter($sheet['days'], fn (array $day) => $day['total_seconds'] > 0))),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('YYYY-MM-DD')->required(),
            'to' => $schema->string()->description('YYYY-MM-DD')->required(),
        ];
    }
}
