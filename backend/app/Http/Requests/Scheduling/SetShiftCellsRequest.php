<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Identity\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** RF-06.02 free selection: `cells` = [{user_id, date}], same template (null clears). */
class SetShiftCellsRequest extends FormRequest
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
            'cells' => ['required', 'array', 'min:1'],
            'cells.*.user_id' => ['required', 'integer'],
            'cells.*.date' => ['required', 'date_format:Y-m-d'],
            'shift_template_id' => ['nullable', 'integer'],
        ];
    }
}
