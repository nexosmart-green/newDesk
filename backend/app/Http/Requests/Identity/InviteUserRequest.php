<?php

namespace App\Http\Requests\Identity;

use App\Domain\Identity\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('company_id', $this->user()->company_id)],
        ];
    }

    /** @return array{first_name: string, last_name: string, email: string} */
    public function personData(): array
    {
        return [
            'first_name' => $this->string('first_name')->trim()->value(),
            'last_name' => $this->string('last_name')->trim()->value(),
            'email' => $this->string('email')->trim()->lower()->value(),
        ];
    }

    public function role(): Role
    {
        return Role::query()->findOrFail($this->integer('role_id'));
    }
}
