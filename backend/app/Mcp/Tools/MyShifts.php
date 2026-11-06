<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Queries\Calendar;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('my_shifts')]
#[Description('Mis turnos entre dos fechas (máx. 62 días), con horario local de la empresa, modalidad y feriados.')]
#[IsReadOnly]
class MyShifts extends NewDeskTool
{
    public function __construct(private readonly Calendar $calendar) {}

    protected function run(Request $request, User $user): Response
    {
        $input = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        if (now()->parse($input['from'])->diffInDays(now()->parse($input['to'])) > 62) {
            return Response::error('El rango máximo es de 62 días.');
        }

        $data = $this->calendar->forPerson($user, $input['from'], $input['to']);

        return $this->json([
            'timezone' => $data['timezone'],
            'shifts' => array_map(fn (array $shift) => [
                'date' => $shift['date'],
                'shift' => $shift['name'],
                'from' => $shift['starts_at'],
                'to' => $shift['ends_at'],
                'mode' => $shift['mode_label'],
            ], $data['shifts']),
            'holidays' => array_map(fn (array $holiday) => ['date' => $holiday['date'], 'name' => $holiday['name']], $data['holidays']),
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
