<?php

namespace App\Http\Requests\People;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /people` directory filters (RF-01.07). Only people admins may include inactive users.
 */
class PeopleDirectoryRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'area_id' => ['nullable', 'integer'],
            'skill_id' => ['nullable', 'integer'],
            'min_level' => ['nullable', 'integer', 'between:0,100'],
            'include_inactive' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
        ];
    }

    /** @return array{search: string|null, area_id: int|null, skill_id: int|null, min_level: int|null, include_inactive: bool} */
    public function filters(): array
    {
        return [
            'search' => $this->validated('search'),
            'area_id' => $this->optionalInt('area_id'),
            'skill_id' => $this->optionalInt('skill_id'),
            'min_level' => $this->optionalInt('min_level'),
            'include_inactive' => $this->boolean('include_inactive') && $this->user()->can('viewAny', User::class),
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 50);
    }

    private function optionalInt(string $key): ?int
    {
        $value = $this->validated($key);

        return $value === null ? null : (int) $value;
    }
}
