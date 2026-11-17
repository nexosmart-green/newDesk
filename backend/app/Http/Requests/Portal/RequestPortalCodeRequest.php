<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/** RF-13.07: ask for a login code. */
class RequestPortalCodeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:254']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email' => 'Ingresá un mail válido.'];
    }
}
