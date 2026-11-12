<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Tasks\Models\Task;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `POST /projects/{project}/task-images`: whoever may create tasks in the project may upload images for them.
 * Only size here: the format is read from the bytes in StoreTaskImage.
 */
class StoreTaskImageRequest extends FormRequest
{
    private const MAX_KILOBYTES = 5120;

    public function authorize(): Response
    {
        return Gate::inspect('create', [Task::class, $this->route('project')]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['image' => ['required', 'file', 'max:'.self::MAX_KILOBYTES]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.required' => 'Elegí una imagen para subir.',
            'image.file' => 'Elegí una imagen para subir.',
            'image.uploaded' => 'La imagen no se pudo subir: puede pesar hasta 5 MB.',
            'image.max' => 'La imagen puede pesar hasta 5 MB.',
        ];
    }
}
