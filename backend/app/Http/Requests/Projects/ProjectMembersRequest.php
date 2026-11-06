<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF-03.03: members are team users of the same company. */
class ProjectMembersRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')
                ->where('company_id', $this->user()->company_id)
                ->whereNull('client_id')],
        ];
    }
}
