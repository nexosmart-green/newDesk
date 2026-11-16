<?php

namespace App\Http\Requests;

use App\Domain\Shared\Support\DateRange;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `from`/`to` local days (both included) of a screen with a maximum span. Subclasses say how many days.
 * Format errors come from validation; «Hasta» before «Desde» and a too-long span from DateRange (field `to`).
 */
abstract class LocalDateRangeRequest extends FormRequest
{
    abstract protected function maxDays(): int;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function range(): DateRange
    {
        return DateRange::of($this->validated('from'), $this->validated('to'), $this->maxDays());
    }
}
