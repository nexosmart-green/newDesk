<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Scheduling\Enums\WorkMode;
use App\Domain\Scheduling\Models\ShiftTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveShiftTemplateRequest extends FormRequest
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
        $template = $this->route('shiftTemplate');

        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('shift_templates', 'name')
                ->where('company_id', $this->user()->company_id)->whereNull('deleted_at')
                ->ignore($template instanceof ShiftTemplate ? $template->id : null)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'mode' => ['required', Rule::enum(WorkMode::class)],
        ];
    }
}
