<?php

namespace App\Http\Requests\Projects;

use App\Domain\Projects\Enums\ProjectStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * `PUT /projects/{project}/status`. Authorization first: outsiders get 404 (CA-03.03).
 */
class ChangeProjectStatusRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('project'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ProjectStatus::class)]];
    }

    public function status(): ProjectStatus
    {
        return ProjectStatus::from($this->string('status')->value());
    }
}
