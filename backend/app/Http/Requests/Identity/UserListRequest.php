<?php

namespace App\Http\Requests\Identity;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * `GET /users?status&search` (user administration). Authorization runs before validation.
 */
class UserListRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', User::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array{status: string|null, search: string|null} */
    public function filters(): array
    {
        return [
            'status' => $this->query('status'),
            'search' => $this->query('search'),
        ];
    }
}
