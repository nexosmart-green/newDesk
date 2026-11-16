<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Support\TaskLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Create (title required) and update (partial). Membership of assignees is checked in the domain.
 */
class SaveTaskRequest extends FormRequest
{
    /** Authorize BEFORE validating: outsiders get 404 even with an invalid body (CA-03.03). */
    public function authorize(): bool
    {
        $task = $this->route('task');
        if ($task instanceof Task) {
            Gate::authorize('update', $task);
        } elseif (($project = $this->route('project')) instanceof Project) {
            Gate::authorize('create', [Task::class, $project]);
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $presence = $this->route('task') instanceof Task ? 'sometimes' : 'required';
        $companyId = $this->user()->company_id;

        return [
            'title' => [$presence, 'string', 'max:'.TaskLimits::TITLE_MAX],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.TaskLimits::DESCRIPTION_MAX],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'due_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_on'],
            'estimate_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:3600000'],
            'assignee_ids' => ['sometimes', 'array', 'max:'.TaskLimits::ASSIGNEES_MAX],
            'assignee_ids.*' => ['integer', 'distinct'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_categories', 'id')->where('company_id', $companyId)],
            'label_ids' => ['sometimes', 'array', 'max:'.TaskLimits::LABELS_MAX],
            'label_ids.*' => ['integer', 'distinct', Rule::exists('task_labels', 'id')->where('company_id', $companyId)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'La categoría elegida ya no existe. Recargá y elegí otra.',
            'label_ids.*.exists' => 'Una de las etiquetas elegidas ya no existe. Recargá y elegí de nuevo.',
            'label_ids.max' => 'Una tarea admite hasta '.TaskLimits::LABELS_MAX.' etiquetas.',
        ];
    }

    /** @return array<string, mixed> Task::EDITABLE fields plus `label_ids` when sent */
    public function taskData(): array
    {
        $data = $this->safe()->except('assignee_ids');
        if (array_key_exists('category_id', $data)) {
            $data['category_id'] = $data['category_id'] === null ? null : (int) $data['category_id'];
        }
        if (array_key_exists('label_ids', $data)) {
            $data['label_ids'] = array_map('intval', $data['label_ids']);
        }

        return $data;
    }

    /** @return list<int> */
    public function assigneeIds(): array
    {
        return array_map('intval', $this->validated('assignee_ids', []));
    }
}
