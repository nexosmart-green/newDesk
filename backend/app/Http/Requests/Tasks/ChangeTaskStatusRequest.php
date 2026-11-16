<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Tasks\Models\TaskStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `PUT /tasks/{task}/status`. Authorization first: outsiders get 404 (CA-03.03).
 */
class ChangeTaskStatusRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('task'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['status_id' => ['required', 'integer']];
    }

    public function status(): TaskStatus
    {
        return TaskStatus::query()->findOrFail($this->integer('status_id'));
    }
}
