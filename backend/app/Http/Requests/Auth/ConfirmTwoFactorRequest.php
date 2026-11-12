<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Last step of turning 2FA on: the first code the authenticator app shows.
 */
class ConfirmTwoFactorRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:10']];
    }
}
