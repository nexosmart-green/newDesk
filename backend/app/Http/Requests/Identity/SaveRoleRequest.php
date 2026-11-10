<?php

namespace App\Http\Requests\Identity;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('roles', 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($role instanceof Role ? $role->id : null),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::enum(Permission::class)],
        ];
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return array_map(fn (string $value) => Permission::from($value), $this->input('permissions', []));
    }
}
