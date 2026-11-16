<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `PUT /tasks/{task}/move` (RF-04.12). The policy needs the destination, so it is authorized in the controller.
 */
class MoveTaskRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['project_id' => ['required', 'integer']];
    }

    public function destination(): Project
    {
        return Project::query()->findOrFail($this->integer('project_id'));
    }
}
