<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Tasks\Support\TaskLimits;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `PUT /tasks/{task}/assignees`: the full list of assignees. Membership is checked in AssignTask.
 */
class SetTaskAssigneesRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('assign', $this->route('task'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'assignee_ids' => ['present', 'array', 'max:'.TaskLimits::ASSIGNEES_MAX],
            'assignee_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return list<int> */
    public function assigneeIds(): array
    {
        return array_map('intval', $this->input('assignee_ids'));
    }
}
