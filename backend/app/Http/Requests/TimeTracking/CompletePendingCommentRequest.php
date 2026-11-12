<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** RF-05.17. The minimum length is a domain rule (TimerRules), same message on web and MCP. */
class CompletePendingCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('completeComment', $this->route('timeEntry'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['comment' => ['nullable', 'string', 'max:'.TimeTrackingLimits::ENTRY_COMMENT_MAX]];
    }
}
