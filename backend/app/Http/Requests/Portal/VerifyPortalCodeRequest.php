<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/** RF-13.07: enter with the mailed code. */
class VerifyPortalCodeRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'code' => ['required', 'string', 'max:16'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email' => 'Ingresá un mail válido.', 'code' => 'Ingresá el código que te enviamos.'];
    }
}
