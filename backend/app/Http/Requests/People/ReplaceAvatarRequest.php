<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /me/avatar`. Only size here: the image type is read from the bytes in ReplaceAvatar.
 */
class ReplaceAvatarRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['avatar' => ['required', 'file', 'max:5120']];
    }
}
