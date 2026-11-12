<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** `previous_comment` closes the timer that was running (switching tasks). */
class StartTimerRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('task'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['previous_comment' => ['nullable', 'string', 'max:'.TimeTrackingLimits::ENTRY_COMMENT_MAX]];
    }
}
