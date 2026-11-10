<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\Identity\Enums\Permission;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filters of "Historial de horas" (page and CSV). Own entries by default; someone else's need `time.manage`.
 */
class TimeEntryHistoryRequest extends FormRequest
{
    public const MAX_RANGE_DAYS = 366;

    public function authorize(): Response
    {
        if (! $this->filled('user_id') || (int) $this->input('user_id') === $this->user()->id
            || $this->user()->hasPermission(Permission::TimeManage)) {
            return Response::allow();
        }

        return Response::deny('Solo quien gestiona horas puede ver los registros de otras personas.');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'project_id' => ['sometimes', 'nullable', 'integer'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('company_id', $this->user()->company_id)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from.required' => 'Elegí la fecha desde.',
            'from.date_format' => 'La fecha desde tiene que ser AAAA-MM-DD.',
            'to.required' => 'Elegí la fecha hasta.',
            'to.date_format' => 'La fecha hasta tiene que ser AAAA-MM-DD.',
            'to.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.',
            'user_id.exists' => 'Esa persona no existe.',
            'search.max' => 'La búsqueda puede tener hasta 100 caracteres.',
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $days = Carbon::parse($this->input('from'))->diffInDays(Carbon::parse($this->input('to'))) + 1;
            if ($days > self::MAX_RANGE_DAYS) {
                $validator->errors()->add('to', 'El rango puede ser de hasta '.self::MAX_RANGE_DAYS.' días. Acortalo o exportá por partes.');
            }
        }];
    }

    /** @return array{user_id: int, from: string, to: string, timezone: string, project_id: int|null, search: string|null} */
    public function filters(): array
    {
        return [
            'user_id' => $this->filled('user_id') ? $this->integer('user_id') : $this->user()->id,
            'from' => $this->string('from')->value(),
            'to' => $this->string('to')->value(),
            'timezone' => $this->user()->effectiveTimezone(),
            'project_id' => $this->filled('project_id') ? $this->integer('project_id') : null,
            'search' => $this->validated('search'),
        ];
    }
}
