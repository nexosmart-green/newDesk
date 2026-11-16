<?php

namespace App\Http\Requests\Clients;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * `GET /clients?status&search&per_page`. Authorization runs before validation.
 */
class ClientListRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 50;

    private const MAX_PER_PAGE = 200;

    public function authorize(): Response
    {
        return Gate::inspect('viewAny', Client::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(ClientStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array{status: string|null, search: string|null} */
    public function filters(): array
    {
        return [
            'status' => $this->query('status'),
            'search' => $this->query('search'),
        ];
    }

    /** `per_page` is not validated: out-of-range values are clamped to 1..200. */
    public function perPage(): int
    {
        return min(max($this->integer('per_page', self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);
    }
}
