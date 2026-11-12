<?php

namespace App\Http\Requests\Projects;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `POST /projects/{project}/members` (RF-03.03). Whether the person may join is decided in AddProjectMember.
 */
class AddProjectMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('manageMembers', $this->route('project'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['user_id' => ['required', 'integer']];
    }

    public function userId(): int
    {
        return $this->integer('user_id');
    }
}
