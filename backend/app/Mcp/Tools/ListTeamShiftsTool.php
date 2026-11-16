<?php

namespace App\Mcp\Tools;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use App\Domain\Scheduling\Queries\TeamCalendar;
use App\Domain\Shared\Support\DateRange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('team_shifts')]
#[Description('Turnos del equipo por día ("quién está hoy / esta semana") y feriados, para quien gestiona turnos (todos) o tiene gente a cargo (su equipo). Máximo 31 días. Para mis propios turnos usá my_shifts.')]
#[IsReadOnly]
class ListTeamShiftsTool extends NewDeskTool
{
    protected function module(): ?Permission
    {
        return Permission::ModuleCalendar;
    }

    public function __construct(private readonly TeamCalendar $calendar) {}

    protected function run(Request $request, User $user): Response
    {
        $people = $this->calendar->audienceOf($user);
        Gate::forUser($user)->allowIf($people->isNotEmpty(), 'El calendario de equipo es para quien gestiona turnos o tiene gente a cargo.');
        $input = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d']);
        DateRange::of($input['from'], $input['to'], TeamCalendar::MAX_DAYS);

        $company = $user->company;
        $calendar = ($this->calendar)($people, $company->id, $company->timezone, $input['from'], $input['to']);
        $names = collect($calendar['people'])->pluck('full_name', 'id');

        return $this->json([
            'timezone' => $calendar['timezone'],
            'days' => array_map(fn (array $day) => [
                'date' => $day['date'],
                'holiday' => $day['holiday']['name'] ?? null,
                'shifts' => array_map(fn (array $shift) => [
                    'person' => $names[$shift['user_id']] ?? null,
                    'from' => $shift['starts_at'],
                    'to' => $shift['ends_at'],
                    'mode' => $shift['mode'],
                    'shift' => $shift['template_name'],
                ], $day['shifts']),
            ], $calendar['days']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Desde, YYYY-MM-DD.')->required(),
            'to' => $schema->string()->description('Hasta, YYYY-MM-DD (incluido; máximo 31 días).')->required(),
        ];
    }
}
