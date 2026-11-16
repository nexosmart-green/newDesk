<?php

namespace App\Http\Requests\TimeTracking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** `from`/`to` are local dates of the viewer (an entry at 23:30 belongs to that day, not the next). */
class ProjectTimeEntriesRequest extends FormRequest
{
    /** Authorize BEFORE validating: no permission → 403 (or 404 when it must not know it exists), whatever the body. */
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('project'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer'],
            'task_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array{user_id: int|null, task_id: int|null, from: string|null, to: string|null} */
    public function filters(): array
    {
        return [
            'user_id' => $this->filled('user_id') ? $this->integer('user_id') : null,
            'task_id' => $this->filled('task_id') ? $this->integer('task_id') : null,
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
        ];
    }
}
