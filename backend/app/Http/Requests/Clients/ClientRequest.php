<?php

namespace App\Http\Requests\Clients;

use App\Domain\Clients\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** RF-02.01 (create and update). */
class ClientRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $client = $this->route('client');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('clients')
                ->where('company_id', $this->user()->company_id)
                ->ignore($client instanceof Client ? $client->id : null)],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'tax_id' => ['nullable', 'string', 'max:20'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => 'Ya hay un cliente con ese nombre.'];
    }
}
