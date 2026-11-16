<?php

namespace App\Http\Requests\Mcp;

use App\Domain\Mcp\Actions\IssueMcpToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueMcpTokenRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // `assistant:` names the assistant's ephemeral tokens, which the token lists leave out.
            'name' => ['required', 'string', 'max:60', 'not_regex:/^\s*assistant:/i'],
            'expires_in_days' => ['nullable', 'integer', Rule::in(IssueMcpToken::EXPIRY_OPTIONS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.not_regex' => 'Ese nombre está reservado para el asistente. Elegí otro.'];
    }

    public function tokenName(): string
    {
        return $this->string('name')->value();
    }

    public function expiresInDays(): ?int
    {
        return $this->filled('expires_in_days') ? $this->integer('expires_in_days') : null;
    }
}
