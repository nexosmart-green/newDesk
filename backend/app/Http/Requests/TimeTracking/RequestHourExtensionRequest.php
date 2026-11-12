<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RequestHourExtensionRequest extends FormRequest
{
    /** Authorize BEFORE validating: no permission → 403 (or 404 when it must not know it exists), whatever the body. */
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('project'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['message' => ['nullable', 'string', 'max:'.TimeTrackingLimits::EXTENSION_MESSAGE_MAX]];
    }
}
