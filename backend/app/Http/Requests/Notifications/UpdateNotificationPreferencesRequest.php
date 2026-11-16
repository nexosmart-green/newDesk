<?php

namespace App\Http\Requests\Notifications;

use App\Domain\Notifications\Enums\NotificationEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array'],
            'preferences.*.event' => ['required', Rule::enum(NotificationEvent::class)],
            'preferences.*.email' => ['required', 'boolean'],
        ];
    }

    /** @return list<array{event: string, email: bool}> */
    public function preferences(): array
    {
        return $this->validated('preferences');
    }
}
