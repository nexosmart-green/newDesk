<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Tasks\Enums\StatusKind;
use App\Domain\Tasks\Models\TaskStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Create (`POST /task-statuses`) or edit (`PUT /task-statuses/{taskStatus}`) a board status (RF-04.03 / RF-11.04).
 */
class SaveTaskStatusRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect(Permission::CompanyManage->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $status = $this->route('taskStatus');

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('task_statuses', 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($status instanceof TaskStatus ? $status->id : null),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'kind' => ['required', Rule::enum(StatusKind::class)],
        ];
    }

    /** @return array{name: string, color: string, kind: string} */
    public function statusData(): array
    {
        return $this->validated();
    }
}
