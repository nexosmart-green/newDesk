<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Scheduling\Actions\AssignShifts;
use App\Domain\Scheduling\Actions\CopyPreviousWeek;
use App\Domain\Scheduling\Actions\SaveShiftTemplate;
use App\Domain\Scheduling\Enums\WorkMode;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Scheduling\Queries\Calendar;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * RF-06.01/02/05: templates, the weekly grid (people × days) and bulk assignment. `shifts.manage`.
 */
class ShiftController extends Controller
{
    public function templates(): JsonResponse
    {
        return ApiResponse::ok(ShiftTemplate::query()->orderBy('starts_at')->get()->map(fn (ShiftTemplate $template) => $this->presentTemplate($template)));
    }

    public function storeTemplate(Request $request, SaveShiftTemplate $save): JsonResponse
    {
        Gate::authorize(Permission::ShiftsManage->value);

        return ApiResponse::ok($this->presentTemplate($save($request->user(), $this->validatedTemplate($request))), 'Plantilla creada.', 201);
    }

    public function updateTemplate(Request $request, ShiftTemplate $shiftTemplate, SaveShiftTemplate $save): JsonResponse
    {
        Gate::authorize(Permission::ShiftsManage->value);

        return ApiResponse::ok($this->presentTemplate($save($request->user(), $this->validatedTemplate($request, $shiftTemplate->id), $shiftTemplate)), 'Plantilla actualizada.');
    }

    public function destroyTemplate(ShiftTemplate $shiftTemplate): JsonResponse
    {
        Gate::authorize(Permission::ShiftsManage->value);
        $shiftTemplate->delete(); // soft: assigned shifts keep their own times

        return ApiResponse::ok(message: 'Plantilla borrada.');
    }

    public function grid(Request $request, Calendar $calendar): JsonResponse
    {
        Gate::authorize(Permission::ShiftsManage->value);
        $range = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $company = $request->user()->company;

        return ApiResponse::ok($calendar->forTeam($company->id, $company->timezone, $range['from'], $range['to']));
    }

    public function assign(Request $request, AssignShifts $assignShifts): JsonResponse
    {
        Gate::authorize(Permission::ShiftsManage->value);
        $input = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct'],
            'shift_template_id' => ['nullable', 'integer'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'weekdays' => ['sometimes', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
        ]);
        $template = isset($input['shift_template_id']) ? ShiftTemplate::query()->findOrFail($input['shift_template_id']) : null;

        $cells = $assignShifts($request->user(), array_map('intval', $input['user_ids']), $template, $input['from'], $input['to'], array_map('intval', $input['weekdays'] ?? []));

        return ApiResponse::ok(['cells' => $cells], $template ? 'Turnos asignados.' : 'Turnos quitados.');
    }

    public function copyPreviousWeek(Request $request, CopyPreviousWeek $copy): JsonResponse
    {
        Gate::authorize(Permission::ShiftsManage->value);
        $request->validate(['week_start' => ['required', 'date_format:Y-m-d']]);

        return ApiResponse::ok(['copied' => $copy($request->user(), $request->string('week_start')->value())], 'Semana copiada.');
    }

    /** @return array{name: string, starts_at: string, ends_at: string, color: string, mode: string} */
    private function validatedTemplate(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('shift_templates', 'name')->where('company_id', $request->user()->company_id)->whereNull('deleted_at')->ignore($ignoreId)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'mode' => ['required', Rule::enum(WorkMode::class)],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentTemplate(ShiftTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'starts_at' => substr($template->starts_at, 0, 5),
            'ends_at' => substr($template->ends_at, 0, 5),
            'color' => $template->color,
            'mode' => $template->mode->value,
        ];
    }
}
