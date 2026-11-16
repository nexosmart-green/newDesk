<?php

namespace App\Domain\Projects\Support;

use App\Domain\Projects\Enums\ProjectType;
use App\Domain\Projects\Models\Project;
use Illuminate\Validation\Rule;

/**
 * Validation of a project's editable fields, shared by the web (SaveProjectRequest) and MCP (create/update_project).
 * Create requires the core fields; update is partial. Client/member activity is checked in the domain.
 */
final class ProjectRules
{
    /** @return array<string, mixed> */
    public static function save(int $companyId, ?Project $existing = null): array
    {
        $presence = $existing instanceof Project ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:150'],
            'code_prefix' => [
                $presence, 'string', 'min:2', 'max:10', 'regex:/^[a-zA-Z][a-zA-Z0-9]*$/',
                Rule::unique('projects', 'code_prefix')->where('company_id', $companyId)->ignore($existing?->id),
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
}
