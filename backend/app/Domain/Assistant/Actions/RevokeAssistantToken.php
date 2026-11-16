<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Models\AssistantMessage;
use Laravel\Sanctum\PersonalAccessToken;

final class RevokeAssistantToken
{
    public function __invoke(AssistantMessage $answer): void
    {
        if ($answer->access_token_id !== null) {
            PersonalAccessToken::query()->whereKey($answer->access_token_id)->delete();
        }
    }
}
