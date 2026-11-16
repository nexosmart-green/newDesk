<?php

namespace App\Http\Requests\Projects;

use App\Domain\Projects\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `GET /projects` filters (RF-03.07).
 */
class ProjectListRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'running_out' => ['nullable', 'boolean'],
        ];
    }

    /** @return array{client_id: int|null, status: string|null, search: string|null, running_out: bool} */
    public function filters(): array
    {
        $clientId = $this->validated('client_id');

        return [
            'client_id' => $clientId === null ? null : (int) $clientId,
            'status' => $this->validated('status'),
            'search' => $this->validated('search'),
            'running_out' => $this->boolean('running_out'),
        ];
    }
}
