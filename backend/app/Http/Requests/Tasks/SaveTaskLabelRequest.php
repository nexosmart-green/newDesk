<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Tasks\Models\TaskLabel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskLabelRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $label = $this->route('taskLabel');

        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('task_labels', 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($label instanceof TaskLabel ? $label->id : null),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Poné un nombre para la etiqueta.',
            'name.max' => 'El nombre puede tener hasta 80 caracteres.',
            'name.unique' => "Ya existe la etiqueta {$this->labelName()}.",
            'color.required' => 'Elegí un color para la etiqueta.',
            'color.regex' => 'El color tiene que tener el formato #rrggbb.',
        ];
    }

    public function labelName(): string
    {
        return $this->string('name')->trim()->value();
    }
}
