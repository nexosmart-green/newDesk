<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Scheduling\Queries\TeamCalendar;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Shared\Support\DateRange;
use Illuminate\Foundation\Http\FormRequest;
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
            'from.date_format' => 'Escribí la fecha de inicio con el formato AAAA-MM-DD.',
            'to.required' => 'Indicá hasta qué día.',
            'to.date_format' => 'Escribí la fecha de fin con el formato AAAA-MM-DD.',
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
            try {
                DateRange::of($this->string('from')->value(), $this->string('to')->value(), TeamCalendar::MAX_DAYS);
            } catch (BusinessRuleViolation $e) {
                $validator->errors()->add('to', $e->getMessage());
            }
        }];
    }
}
