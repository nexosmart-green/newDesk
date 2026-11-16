<?php

namespace App\Http\Requests\Projects;

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\ProjectRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

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

        return ProjectRules::save($this->user()->company_id, $project instanceof Project ? $project : null);
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
