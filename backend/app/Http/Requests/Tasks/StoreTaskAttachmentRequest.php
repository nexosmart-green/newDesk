<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Tasks\Models\TaskAttachment;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `POST /tasks/{task}/attachments` (RF-04.09). Only size here: the type is read from the bytes in StoreTaskAttachment.
 */
class StoreTaskAttachmentRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('task'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:'.TaskAttachment::MAX_KILOBYTES]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Elegí un archivo para subir.',
            'file.file' => 'Elegí un archivo para subir.',
            'file.uploaded' => 'El archivo no se pudo subir: puede pesar hasta 20 MB.',
            'file.max' => 'El archivo puede pesar hasta 20 MB.',
        ];
    }
}
