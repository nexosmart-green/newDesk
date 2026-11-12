<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Support\LocalTimeRange;
use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** RF-05.15: the comment is mandatory; date + from/to (person's timezone) only when the times change. */
class CorrectTimeEntryRequest extends FormRequest
{
    /** Authorize BEFORE validating: other people's entries are 404 even with an invalid body. */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('timeEntry'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required_with:from,to', 'date_format:Y-m-d'],
            'from' => ['required_with:date', 'date_format:H:i'],
            'to' => ['required_with:date', 'date_format:H:i'],
            'comment' => ['required', 'string', 'max:'.TimeTrackingLimits::ENTRY_COMMENT_MAX],
        ];
    }

    /** @return array{0: Carbon|null, 1: Carbon|null} null when the times stay as they are */
    public function utcRange(): array
    {
        return $this->filled('date')
            ? LocalTimeRange::toUtc($this->user(), $this->validated('date'), $this->validated('from'), $this->validated('to'))
            : [null, null];
    }
}
