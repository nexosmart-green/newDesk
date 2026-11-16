<?php

namespace App\Http\Requests\Tasks;

use App\Domain\Tasks\Support\TaskLimits;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `POST /tasks/{task}/comments`. Whether mentioned people may be notified is decided in CommentOnTask.
 */
class CommentOnTaskRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('comment', $this->route('task'));
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.TaskLimits::COMMENT_BODY_MAX],
            'mentioned_user_ids' => ['sometimes', 'array', 'max:'.TaskLimits::MENTIONS_MAX],
            'mentioned_user_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->trim()->value();
    }

    /** @return list<int> */
    public function mentionedUserIds(): array
    {
        return array_map('intval', $this->input('mentioned_user_ids', []));
    }
}
