<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Identity\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** RF-06.02 bulk: several people × a date range, optionally filtered by ISO weekdays. */
class AssignShiftsRequest extends FormRequest
{
    /** Authorize BEFORE validating: no permission → 403 (or 404 when it must not know it exists), whatever the body. */
    public function authorize(): bool
    {
        Gate::authorize(Permission::ShiftsManage->value);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct'],
            'shift_template_id' => ['nullable', 'integer'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'weekdays' => ['sometimes', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
        ];
    }

    /** @return list<int> */
    public function userIds(): array
    {
        return array_map('intval', $this->validated('user_ids'));
    }

    /** @return list<int> */
    public function weekdays(): array
    {
        return array_map('intval', $this->validated('weekdays') ?? []);
    }
}
