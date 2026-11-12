<?php

namespace App\Http\Requests\Clients;

use App\Domain\Clients\Enums\ClientStatus;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * `PUT /clients/{client}/status`. Authorization runs before validation.
 */
class ChangeClientStatusRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('client'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ClientStatus::class)]];
    }

    public function status(): ClientStatus
    {
        return ClientStatus::from($this->string('status')->value());
    }
}
