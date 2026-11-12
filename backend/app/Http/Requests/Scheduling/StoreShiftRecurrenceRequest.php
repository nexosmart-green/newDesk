<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Identity\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * `POST /shift-recurrences` (RF-06.03): one rule per person. Dates are local to the company.
 */
class StoreShiftRecurrenceRequest extends FormRequest
{
    public const MAX_PEOPLE = 100;

    public function authorize(): bool
    {
        return Gate::allows(Permission::ShiftsManage->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PEOPLE],
            'user_ids.*' => ['integer', 'distinct'],
            'shift_template_id' => ['required', 'integer', Rule::exists('shift_templates', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->today()],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'skip_holidays' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'Elegí al menos una persona.',
            'user_ids.min' => 'Elegí al menos una persona.',
            'user_ids.array' => 'Elegí al menos una persona.',
            'user_ids.max' => 'Podés armar turnos fijos para hasta '.self::MAX_PEOPLE.' personas a la vez.',
            'user_ids.*.integer' => 'Alguna persona elegida no es válida.',
            'user_ids.*.distinct' => 'Elegiste la misma persona dos veces.',
            'shift_template_id.required' => 'Elegí una plantilla de turno.',
            'shift_template_id.integer' => 'Elegí una plantilla de turno.',
            'shift_template_id.exists' => 'Esa plantilla no existe o fue borrada.',
            'weekdays.required' => 'Elegí al menos un día de la semana.',
            'weekdays.min' => 'Elegí al menos un día de la semana.',
            'weekdays.array' => 'Elegí al menos un día de la semana.',
            'weekdays.max' => 'Una semana tiene 7 días.',
            'weekdays.*.integer' => 'Los días van del lunes (1) al domingo (7).',
            'weekdays.*.between' => 'Los días van del lunes (1) al domingo (7).',
            'weekdays.*.distinct' => 'Repetiste un día de la semana.',
            'starts_on.required' => 'Indicá desde cuándo.',
            'starts_on.date_format' => 'La fecha de inicio no es válida.',
            'starts_on.after_or_equal' => 'El turno fijo no puede empezar antes de hoy.',
            'ends_on.date_format' => 'La fecha de fin no es válida.',
            'ends_on.after_or_equal' => 'La fecha de fin no puede ser anterior al inicio.',
            'skip_holidays.boolean' => 'Indicá si se saltean los feriados.',
        ];
    }

    /** @return list<int> */
    public function userIds(): array
    {
        return array_map('intval', $this->input('user_ids'));
    }

    /** @return list<int> */
    public function weekdays(): array
    {
        return array_map('intval', $this->input('weekdays'));
    }

    private function today(): string
    {
        return now($this->user()->company->timezone)->toDateString();
    }
}
