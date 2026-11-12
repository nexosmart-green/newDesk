<?php

namespace App\Http\Requests\Companies;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `POST /company/logo`. Authorization runs before validation.
 */
class ReplaceCompanyLogoRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->user()->company);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        // No SVG: it can carry scripts.
        return ['logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']];
    }
}
