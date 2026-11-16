<?php

namespace App\Http\Requests\Scheduling;

use Illuminate\Foundation\Http\FormRequest;

class HolidayListRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['year' => ['required', 'integer', 'between:2000,2100']];
    }
}
