<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Identity\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveHolidayRequest extends FormRequest
{
    /** Authorize BEFORE validating: no permission → 403 (or 404 when it must not know it exists), whatever the body. */
    public function authorize(): bool
    {
        Gate::authorize(Permission::CompanyManage->value);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::in(['national', 'company'])],
        ];
    }
}
