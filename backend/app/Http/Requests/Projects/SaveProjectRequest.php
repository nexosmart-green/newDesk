<?php

namespace App\Http\Requests\Projects;

use App\Domain\Projects\Enums\ProjectType;
use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Create (all required) and update (partial) share the rules. Client/member activity is checked in the domain.
 */
class SaveProjectRequest extends FormRequest
{
    /** Authorize BEFORE validating: a non-member gets 404 even with an invalid body (CA-03.03). */
    public function authorize(): bool
    {
        $project = $this->route('project');
        Gate::authorize(...$project instanceof Project ? ['update', $project] : ['create', Project::class]);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $project = $this->route('project');
        $presence = $project instanceof Project ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:150'],
            'code_prefix' => [
                $presence, 'string', 'min:2', 'max:10', 'regex:/^[a-zA-Z][a-zA-Z0-9]*$/',
                Rule::unique('projects', 'code_prefix')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($project instanceof Project ? $project->id : null),
            ],
            'type' => [$presence, Rule::enum(ProjectType::class)],
            'client_id' => ['sometimes', 'nullable', 'integer'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'leader_user_id' => ['sometimes', 'nullable', 'integer'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_on'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array<string, mixed> */
    public function projectData(): array
    {
        $data = $this->safe()->except('member_ids');
        if (isset($data['code_prefix'])) {
            $data['code_prefix'] = mb_strtolower($data['code_prefix']);
        }

        return $data;
    }

    /** @return list<int> */
    public function memberIds(): array
    {
        return array_map('intval', $this->validated('member_ids', []));
    }
}
