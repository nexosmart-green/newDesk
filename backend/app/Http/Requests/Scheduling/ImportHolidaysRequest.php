<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Identity\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ImportHolidaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize(Permission::CompanyManage->value);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $year = now()->year;

        return ['year' => ['required', 'integer', 'between:'.($year - 1).','.($year + 1)]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['year.between' => 'Se pueden importar los feriados del año pasado, este o el que viene.'];
    }
}
