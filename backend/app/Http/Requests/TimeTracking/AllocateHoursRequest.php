<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** RF-05.04: seconds may be negative (a correction), never zero. */
class AllocateHoursRequest extends FormRequest
{
    /** Authorize BEFORE validating: no permission → 403 (or 404 when it must not know it exists), whatever the body. */
    public function authorize(): bool
    {
        Gate::authorize('allocateHours', $this->route('project'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $max = TimeTrackingLimits::ALLOCATION_SECONDS_MAX;

        return [
            'seconds' => ['required', 'integer', 'not_in:0', "between:-{$max},{$max}"],
            'reason' => ['required', 'string', 'max:'.TimeTrackingLimits::ALLOCATION_REASON_MAX],
            'reference' => ['nullable', 'string', 'max:'.TimeTrackingLimits::ALLOCATION_REFERENCE_MAX],
        ];
    }
}
