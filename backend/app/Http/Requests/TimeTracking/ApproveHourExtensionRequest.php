<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Approving loads hours: positive seconds only (a correction is a separate allocation). */
class ApproveHourExtensionRequest extends FormRequest
{
    /** Authorize BEFORE validating: no permission → 403 (or 404 when it must not know it exists), whatever the body. */
    public function authorize(): bool
    {
        Gate::authorize('approve', $this->route('extensionRequest'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'seconds' => ['required', 'integer', 'min:1', 'max:'.TimeTrackingLimits::ALLOCATION_SECONDS_MAX],
            'reason' => ['required', 'string', 'max:'.TimeTrackingLimits::ALLOCATION_REASON_MAX],
            'reference' => ['nullable', 'string', 'max:'.TimeTrackingLimits::ALLOCATION_REFERENCE_MAX],
        ];
    }
}
