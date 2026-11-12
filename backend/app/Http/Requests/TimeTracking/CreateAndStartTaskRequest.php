<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Support\TimeTrackingLimits;
use App\Http\Requests\Tasks\SaveTaskRequest;
use Illuminate\Support\Arr;

/** RF-04.02 "Crear e iniciar": a new task plus the comment for the timer it replaces. */
class CreateAndStartTaskRequest extends SaveTaskRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...parent::rules(), 'previous_comment' => ['nullable', 'string', 'max:'.TimeTrackingLimits::ENTRY_COMMENT_MAX]];
    }

    /** @return array<string, mixed> */
    public function taskData(): array
    {
        return Arr::except(parent::taskData(), 'previous_comment');
    }

    public function previousComment(): ?string
    {
        return $this->validated('previous_comment');
    }
}
