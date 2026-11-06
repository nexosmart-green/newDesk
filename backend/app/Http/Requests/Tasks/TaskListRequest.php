<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RF-04.05 filters. Persistence of the chosen filters is per user, on the client.
 */
class TaskListRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'status_ids' => ['sometimes', 'array'],
            'status_ids.*' => ['integer'],
            'include_closed' => ['sometimes', 'boolean'],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
            'project_id' => ['sometimes', 'nullable', 'integer'],
            'overdue' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'between:1,200'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return [
            'status_ids' => array_map('intval', $this->validated('status_ids', [])),
            'include_closed' => $this->boolean('include_closed'),
            'assignee_id' => $this->filled('assignee_id') ? $this->integer('assignee_id') : null,
            'project_id' => $this->filled('project_id') ? $this->integer('project_id') : null,
            'overdue' => $this->boolean('overdue'),
            'search' => $this->validated('search'),
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 50);
    }
}
