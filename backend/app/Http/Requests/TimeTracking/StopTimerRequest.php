<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use Illuminate\Foundation\Http\FormRequest;

class StopTimerRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['comment' => ['nullable', 'string', 'max:'.TimeTrackingLimits::ENTRY_COMMENT_MAX]];
    }
}
