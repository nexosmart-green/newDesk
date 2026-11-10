<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:10', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
