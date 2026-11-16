<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Domain\Assistant\Actions\AskAssistant;
use App\Domain\Assistant\Enums\AssistantChannel;
use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Queries\ConversationMessages;
use App\Domain\Assistant\Queries\MyAssistantConversations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\AskAssistantRequest;
use App\Http\Resources\AssistantConversationResource;
use App\Http\Resources\AssistantMessageResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * RF-13.01: the web channel of the assistant. Asking queues a turn (202); the screen polls the answer.
 */
class AssistantController extends Controller
{
    public function conversations(Request $request, MyAssistantConversations $conversations): AnonymousResourceCollection
    {
        return AssistantConversationResource::collection($conversations($request->user()));
    }

    public function messages(AssistantConversation $conversation, ConversationMessages $messages): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        return AssistantMessageResource::collection($messages($conversation));
    }

    public function ask(AskAssistantRequest $request, AskAssistant $ask): JsonResponse
    {
        $answer = $ask($request->user(), $request->string('text')->value(), AssistantChannel::Web, $request->conversation());

        return ApiResponse::ok(AssistantMessageResource::make($answer->load(ConversationMessages::RELATIONS)), status: 202);
    }

    public function show(AssistantMessage $message): JsonResponse
    {
        Gate::authorize('view', $message->conversation);

        return ApiResponse::ok(AssistantMessageResource::make($message->load(ConversationMessages::RELATIONS)));
    }
}
