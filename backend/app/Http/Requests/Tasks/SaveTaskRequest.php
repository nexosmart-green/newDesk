<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create (title required) and update (partial). Membership of assignees is checked in the domain.
 */
class SaveTaskRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $presence = $this->route('task') instanceof Task ? 'sometimes' : 'required';

        return [
            'title' => [$presence, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65000'],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'due_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_on'],
            'estimate_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3600000'],
            'assignee_ids' => ['sometimes', 'array', 'max:20'],
            'assignee_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array<string, mixed> */
    public function taskData(): array
    {
        return $this->safe()->except('assignee_ids');
    }

    /** @return list<int> */
    public function assigneeIds(): array
    {
        return array_map('intval', $this->validated('assignee_ids', []));
    }
}
