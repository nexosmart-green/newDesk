<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Tasks\Models\TaskChecklistItem;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Adding (`POST /tasks/{task}/checklist`) or editing (`PUT /checklist-items/{item}`) a checklist line.
 * Authorization runs before validation so outsiders get 404, never a validation error.
 */
class SaveChecklistItemRequest extends FormRequest
{
    public function authorize(): Response
    {
        $item = $this->route('checklistItem');

        return $item instanceof TaskChecklistItem
            ? Gate::inspect('update', $item)
            : Gate::inspect('update', $this->route('task'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isNew = ! $this->route('checklistItem') instanceof TaskChecklistItem;

        return [
            'text' => [$isNew ? 'required' : 'sometimes', 'string', 'max:'.TaskChecklistItem::MAX_TEXT_LENGTH],
            'done' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'text.required' => 'Escribí la subtarea.',
            'text.string' => 'Escribí la subtarea.',
            'text.max' => 'La subtarea puede tener hasta '.TaskChecklistItem::MAX_TEXT_LENGTH.' caracteres.',
            'done.boolean' => 'Indicá si la subtarea está hecha o no.',
        ];
    }

    public function text(): ?string
    {
        return $this->has('text') ? $this->string('text')->trim()->value() : null;
    }

    public function done(): ?bool
    {
        return $this->has('done') ? $this->boolean('done') : null;
    }
}
