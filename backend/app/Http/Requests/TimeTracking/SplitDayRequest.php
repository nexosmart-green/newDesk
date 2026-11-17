<?php

namespace App\Http\Requests\TimeTracking;

use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * RF-05.19: `from`/`to` are whole seconds from the day's start (R-5): no timezone or midnight ambiguity.
 * An empty list closes the day counting nothing (CA-05.19i).
 */
class SplitDayRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parts' => ['present', 'array', 'max:50'],
            'parts.*.from' => ['required', 'integer', 'min:0'],
            'parts.*.to' => ['required', 'integer', 'gt:parts.*.from'],
            'parts.*.project_id' => ['required', 'integer'],
            'parts.*.task_id' => ['nullable', 'integer', 'required_without:parts.*.new_task_title'],
            'parts.*.new_task_title' => ['nullable', 'string', 'max:255', 'required_without:parts.*.task_id'],
            'parts.*.comment' => ['required', 'string', 'min:'.TimeEntry::MIN_COMMENT_LENGTH, 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'parts.*.to.gt' => 'El "hasta" tiene que ser después del "desde".',
            'parts.*.project_id.required' => 'Elegí un proyecto.',
            'parts.*.task_id.required_without' => 'Elegí una tarea o escribí una nueva.',
            'parts.*.new_task_title.required_without' => 'Elegí una tarea o escribí una nueva.',
            'parts.*.comment.required' => 'Contá qué hiciste.',
            'parts.*.comment.min' => 'Contá qué hiciste (mínimo '.TimeEntry::MIN_COMMENT_LENGTH.' caracteres).',
        ];
    }

    /** @return list<array{from: int, to: int, project_id: int, task_id: int|null, new_task_title: string|null, comment: string}> */
    public function parts(): array
    {
        return array_map(fn (array $part) => [
            'from' => (int) $part['from'],
            'to' => (int) $part['to'],
            'project_id' => (int) $part['project_id'],
            'task_id' => isset($part['task_id']) ? (int) $part['task_id'] : null,
            'new_task_title' => isset($part['new_task_title']) ? trim($part['new_task_title']) : null,
            'comment' => (string) $part['comment'],
        ], array_values($this->validated('parts')));
    }
}
