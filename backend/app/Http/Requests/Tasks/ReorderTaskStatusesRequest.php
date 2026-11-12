<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Identity\Enums\Permission;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `PUT /task-statuses/order` (RF-04.03): every status id of the company, in the new board order.
 */
class ReorderTaskStatusesRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect(Permission::CompanyManage->value);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct']];
    }

    /** @return list<int> */
    public function orderedIds(): array
    {
        return array_map('intval', $this->input('ids'));
    }
}
