<?php

namespace App\Http\Requests\Identity;

use App\Domain\Identity\Models\Role;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * `PUT /users/{user}/role`. Authorization runs before validation.
 */
class ChangeUserRoleRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('changeRole', $this->route('user'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('company_id', $this->user()->company_id)]];
    }

    public function role(): Role
    {
        return Role::query()->findOrFail($this->integer('role_id'));
    }
}
