<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Support\DateRange;
use App\Domain\Shared\Support\Duration;
use App\Domain\TimeTracking\Queries\Timesheet;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('my_timesheet')]
#[Description('Mis horas por día entre dos fechas (máx. 62 días; para más, pedilo por partes), con el comentario de cada registro.')]
#[IsReadOnly]
class GetMyTimesheetTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleTime;
    }

    public function __construct(private readonly Timesheet $timesheet) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $range = DateRange::of($input['from'], $input['to'], Timesheet::MAX_DAYS);

        $sheet = ($this->timesheet)($user, $range->from, $range->to);

        return $this->json([
            'timezone' => $sheet['timezone'],
            'total' => Duration::humanize($sheet['total_seconds']),
            'days' => array_map(fn (array $day) => [
                'date' => $day['date'],
                'total' => Duration::humanize($day['total_seconds']),
                'entries' => array_map(fn (array $entry) => [
                    'task' => $entry['task']['code'],
                    'time' => Duration::humanize($entry['seconds']),
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
