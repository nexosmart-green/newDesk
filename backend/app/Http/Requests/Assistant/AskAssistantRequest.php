<?php

namespace App\Http\Requests\Assistant;

use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Assistant\Models\AssistantMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * `POST /assistant/messages`: a question, in a new conversation or in one of mine (authorized before validating).
 */
class AskAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->conversation();
        if ($conversation !== null) {
            Gate::authorize('view', $conversation);
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:'.AssistantMessage::CONTENT_MAX],
            'conversation_id' => ['nullable', 'integer'],
        ];
    }

    public function conversation(): ?AssistantConversation
    {
        $id = $this->input('conversation_id');

        return is_numeric($id) ? AssistantConversation::query()->findOrFail((int) $id) : null;
    }
}
