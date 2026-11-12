<?php

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Scheduling\Actions\CreateShiftRecurrences;
use App\Domain\Scheduling\Actions\DeleteShiftRecurrence;
use App\Domain\Scheduling\Actions\EndShiftRecurrence;
use App\Domain\Scheduling\Models\ShiftRecurrence;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Scheduling\Support\RecurrenceGeneration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\EndShiftRecurrenceRequest;
use App\Http\Requests\Scheduling\StoreShiftRecurrenceRequest;
use App\Http\Resources\ShiftRecurrenceResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * RF-06.03 "Turnos fijos": recurring shifts per person. `shifts.manage`.
 */
class ShiftRecurrenceController extends Controller
{
    private const SKIPPED_IN_RESPONSE = 20;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Permission::ShiftsManage->value);
        $request->validate(['user_id' => ['sometimes', 'integer']]);

        $recurrences = ShiftRecurrence::query()->with(['user', 'template'])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->orderByDesc('id')->get();

        return ApiResponse::ok(ShiftRecurrenceResource::collection($recurrences)->resolve($request));
    }

    public function store(StoreShiftRecurrenceRequest $request, CreateShiftRecurrences $create): JsonResponse
    {
        $results = $create(
            $request->user(),
            $request->userIds(),
            ShiftTemplate::query()->findOrFail($request->integer('shift_template_id')),
            $request->weekdays(),
            $request->string('starts_on')->value(),
            $request->input('ends_on'),
            $request->boolean('skip_holidays', true),
        );

        $data = array_map(fn (array $result) => [
            'recurrence' => ShiftRecurrenceResource::make($result['recurrence'])->resolve($request),
            'generated' => count($result['generation']->dates),
            'skipped_count' => count($result['generation']->skipped),
            'skipped' => array_slice($result['generation']->skipped, 0, self::SKIPPED_IN_RESPONSE),
        ], $results);

        return ApiResponse::ok($data, $this->createdMessage(array_column($results, 'generation')), 201);
    }

    public function end(EndShiftRecurrenceRequest $request, ShiftRecurrence $shiftRecurrence, EndShiftRecurrence $end): JsonResponse
    {
        ['recurrence' => $recurrence, 'removed' => $removed] = $end($request->user(), $shiftRecurrence, $request->string('ends_on')->value());

        return ApiResponse::ok(
            ['recurrence' => ShiftRecurrenceResource::make($recurrence)->resolve($request), 'removed' => $removed],
            'Turno fijo finalizado.'.($removed > 0 ? ' Se quitaron '.$this->days($removed).' posteriores.' : ''),
        );
    }

    public function destroy(Request $request, ShiftRecurrence $shiftRecurrence, DeleteShiftRecurrence $delete): JsonResponse
    {
        Gate::authorize('delete', $shiftRecurrence);
        $removed = $delete($request->user(), $shiftRecurrence);

        return ApiResponse::ok(
            ['removed' => $removed],
            'Turno fijo borrado.'.($removed > 0 ? ' Se quitaron '.$this->days($removed).' futuros; los anteriores quedan cargados.' : ''),
        );
    }

    /** "Turno fijo creado: se generaron 40 días (se saltearon 3 feriados y 2 días que ya tenían turno)." */
    private function createdMessage(array $generations): string
    {
        $generated = $holidays = $taken = 0;
        foreach ($generations as $generation) {
            /** @var RecurrenceGeneration $generation */
            $generated += count($generation->dates);
            foreach ($generation->skipped as $skip) {
                $skip['reason'] === RecurrenceGeneration::SKIPPED_HOLIDAY ? $holidays++ : $taken++;
            }
        }

        $skipped = array_filter([
            $holidays > 0 ? ($holidays === 1 ? '1 feriado' : "{$holidays} feriados") : null,
            $taken > 0 ? ($taken === 1 ? '1 día que ya tenía turno' : "{$taken} días que ya tenían turno") : null,
        ]);
        $title = count($generations) === 1 ? 'Turno fijo creado' : 'Turnos fijos creados';

        return "{$title}: se generaron {$this->days($generated)}"
            .($skipped !== [] ? ' (se saltearon '.implode(' y ', $skipped).')' : '').'.';
    }

    private function days(int $count): string
    {
        return $count === 1 ? '1 día' : "{$count} días";
    }
}
