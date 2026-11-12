<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Scheduling\Queries\TeamCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * `GET /team-calendar?from&to` (RF-06.05): local dates, at most a month at a time.
 */
class TeamCalendarRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from.required' => 'Indicá desde qué día.',
            'from.date_format' => 'La fecha de inicio no es válida.',
            'to.required' => 'Indicá hasta qué día.',
            'to.date_format' => 'La fecha de fin no es válida.',
            'to.after_or_equal' => 'La fecha de fin no puede ser anterior al inicio.',
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $days = Carbon::parse($this->string('from')->value())->diffInDays(Carbon::parse($this->string('to')->value())) + 1;
            if ($days > TeamCalendar::MAX_DAYS) {
                $validator->errors()->add('to', 'El calendario de equipo muestra hasta '.TeamCalendar::MAX_DAYS.' días por vez.');
            }
        }];
    }
}
