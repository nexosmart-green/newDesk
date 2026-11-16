<?php

namespace App\Http\Requests\Reports;

use App\Domain\Identity\Enums\Permission;
use App\Domain\TimeTracking\Support\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class HoursReportRequest extends FormRequest
{
    /** Authorize BEFORE validating: no permission → 403 (or 404 when it must not know it exists), whatever the body. */
    public function authorize(): bool
    {
        Gate::authorize(Permission::ProjectsManage->value);
        if ($this->route('project') !== null) {
            Gate::authorize('view', $this->route('project'));
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** Local dates of the viewer; a backwards range is rejected under `to` (ReportPeriod). */
    public function period(): ReportPeriod
    {
        return ReportPeriod::fromInput($this->user(), $this->validated('from'), $this->validated('to'));
    }
}
