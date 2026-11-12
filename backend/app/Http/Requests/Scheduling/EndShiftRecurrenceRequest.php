<?php

namespace App\Http\Requests\Scheduling;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `PUT /shift-recurrences/{id}/end` (RF-06.03). Authorization first: another company's rule is 404.
 */
class EndShiftRecurrenceRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('shiftRecurrence'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['ends_on' => ['required', 'date_format:Y-m-d']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ends_on.required' => 'Indicá hasta qué día va el turno fijo.',
            'ends_on.date_format' => 'Escribí la fecha de fin con el formato AAAA-MM-DD.',
        ];
    }
}
