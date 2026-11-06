<?php

namespace App\Http\Requests\Projects;

use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF-03.01 (create and update). */
class ProjectRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $project = $this->route('project');

        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:120'],
            // CA-03.01: unique per company.
            'code_prefix' => ['required', 'string', 'regex:/^[a-z0-9]{2,12}$/', Rule::unique('projects')
                ->where('company_id', $companyId)
                ->ignore($project instanceof Project ? $project->id : null)],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'leader_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')
                ->where('company_id', $companyId)
                ->whereNull('client_id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code_prefix.unique' => 'Ya hay un proyecto con ese código.',
            'code_prefix.regex' => 'El código va en minúsculas, de 2 a 12 letras o números.',
        ];
    }
}
