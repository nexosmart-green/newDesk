<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Tasks\Models\TaskCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaskCategoryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $category = $this->route('taskCategory');

        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('task_categories', 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($category instanceof TaskCategory ? $category->id : null),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Poné un nombre para la categoría.',
            'name.max' => 'El nombre puede tener hasta 80 caracteres.',
            'name.unique' => "Ya existe la categoría {$this->categoryName()}.",
        ];
    }

    public function categoryName(): string
    {
        return $this->string('name')->trim()->value();
    }
}
