<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Scheduling\Actions\AssignShifts;
use App\Domain\Scheduling\Actions\CopyPreviousWeek;
use App\Domain\Scheduling\Actions\DeleteShiftTemplate;
use App\Domain\Scheduling\Actions\SaveShiftTemplate;
use App\Domain\Scheduling\Actions\SetShiftCells;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Scheduling\Queries\Calendar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\AssignShiftsRequest;
use App\Http\Requests\Scheduling\CopyPreviousWeekRequest;
use App\Http\Requests\Scheduling\SaveShiftTemplateRequest;
use App\Http\Requests\Scheduling\SetShiftCellsRequest;
use App\Http\Requests\Scheduling\ShiftGridRequest;
use App\Http\Resources\ShiftTemplateResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * RF-06.01/02/05: templates, the weekly grid (people × days) and bulk assignment. `shifts.manage`.
 */
class ShiftController extends Controller
{
    public function templates(): AnonymousResourceCollection
    {
        return ShiftTemplateResource::collection(ShiftTemplate::query()->orderBy('starts_at')->get());
    }

    public function storeTemplate(SaveShiftTemplateRequest $request, SaveShiftTemplate $save): JsonResponse
    {
        return ApiResponse::ok(ShiftTemplateResource::make($save($request->user(), $request->validated())), 'Plantilla creada.', 201);
    }

    public function updateTemplate(SaveShiftTemplateRequest $request, ShiftTemplate $shiftTemplate, SaveShiftTemplate $save): JsonResponse
    {
        return ApiResponse::ok(ShiftTemplateResource::make($save($request->user(), $request->validated(), $shiftTemplate)), 'Plantilla actualizada.');
    }

    public function destroyTemplate(Request $request, ShiftTemplate $shiftTemplate, DeleteShiftTemplate $delete): JsonResponse
    {
        Gate::authorize(Permission::ShiftsManage->value);
        $delete($request->user(), $shiftTemplate);

        return ApiResponse::ok(message: 'Plantilla borrada.');
    }

    public function grid(ShiftGridRequest $request, Calendar $calendar): JsonResponse
    {
        $range = $request->range();
        $company = $request->user()->company;

        return ApiResponse::ok($calendar->forTeam($company->id, $company->timezone, $range->from, $range->to));
    }

    public function assign(AssignShiftsRequest $request, AssignShifts $assignShifts): JsonResponse
    {
        $template = $this->template($request->validated('shift_template_id'));

        $cells = $assignShifts($request->user(), $request->userIds(), $template, $request->validated('from'), $request->validated('to'), $request->weekdays());

        return ApiResponse::ok(['cells' => $cells], $template ? 'Turnos asignados.' : 'Turnos quitados.');
    }

    public function setCells(SetShiftCellsRequest $request, SetShiftCells $setCells): JsonResponse
    {
        $template = $this->template($request->validated('shift_template_id'));

        return ApiResponse::ok(['cells' => $setCells($request->user(), $request->validated('cells'), $template)], $template ? 'Turnos asignados.' : 'Turnos quitados.');
    }

    public function copyPreviousWeek(CopyPreviousWeekRequest $request, CopyPreviousWeek $copy): JsonResponse
    {
        return ApiResponse::ok(['copied' => $copy($request->user(), $request->validated('week_start'))], 'Semana copiada.');
    }

    /** `null` clears the cells. */
    private function template(?int $id): ?ShiftTemplate
    {
        return $id !== null ? ShiftTemplate::query()->findOrFail($id) : null;
    }
}
