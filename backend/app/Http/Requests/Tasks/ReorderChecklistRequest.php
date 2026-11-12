<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Tasks\Models\TaskChecklistItem;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `PUT /tasks/{task}/checklist/order` (RF-04.08). Authorization runs before validation.
 */
class ReorderChecklistRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('task'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'ids' => ['present', 'array', 'max:'.TaskChecklistItem::MAX_PER_TASK],
            'ids.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ids.present' => 'Mandá el orden de las subtareas.',
            'ids.array' => 'Mandá el orden de las subtareas.',
            'ids.max' => 'Una tarea tiene como máximo '.TaskChecklistItem::MAX_PER_TASK.' subtareas.',
            'ids.*.integer' => 'El orden tiene un valor que no es una subtarea.',
            'ids.*.distinct' => 'Una subtarea aparece dos veces en el orden.',
        ];
    }

    /** @return list<int> */
    public function orderedIds(): array
    {
        return array_map('intval', $this->input('ids'));
    }
}
