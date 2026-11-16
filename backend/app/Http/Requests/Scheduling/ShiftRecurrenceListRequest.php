<?php

namespace App\Http\Requests\Scheduling;

use App\Domain\Identity\Enums\Permission;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `GET /shift-recurrences?user_id` (RF-06.03). Authorization runs before validation.
 */
class ShiftRecurrenceListRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect(Permission::ShiftsManage->value);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['user_id' => ['sometimes', 'integer']];
    }

    public function userId(): ?int
    {
        return $this->filled('user_id') ? $this->integer('user_id') : null;
    }
}
