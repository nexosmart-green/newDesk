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
            'name' => ['required', 'string', 'max:60'],
            'expires_in_days' => ['nullable', 'integer', Rule::in(IssueMcpToken::EXPIRY_OPTIONS)],
        ];
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
