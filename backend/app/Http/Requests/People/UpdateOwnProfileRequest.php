<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF-01.09: only the fields a person edits about themselves are accepted.
 */
class UpdateOwnProfileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'about' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'locale' => ['sometimes', Rule::in(['es', 'en'])],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
        ];
    }
}
