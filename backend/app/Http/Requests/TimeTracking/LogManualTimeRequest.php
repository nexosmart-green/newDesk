<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Support\LocalTimeRange;
use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** RF-05.09: date + from/to are in the person's timezone; stored in UTC. */
class LogManualTimeRequest extends FormRequest
{
    /** Authorize BEFORE validating: outsiders get 404 even with an invalid body (CA-03.03). */
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('task'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['required', 'date_format:H:i'],
            'to' => ['required', 'date_format:H:i'],
            'comment' => ['nullable', 'string', 'max:'.TimeTrackingLimits::ENTRY_COMMENT_MAX],
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function utcRange(): array
    {
        return LocalTimeRange::toUtc($this->user(), $this->validated('date'), $this->validated('from'), $this->validated('to'));
    }
}
