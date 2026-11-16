<?php

use App\Domain\Assistant\Actions\AnswerAssistantMessage;
use App\Domain\Assistant\Contracts\AssistantEngine;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Support\AssistantAnswer;
use App\Domain\Assistant\Support\AssistantQuestion;
use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/../Mcp/McpTestHelpers.php';

/**
 * Stands in for `claude -p` (tests never call it): the closure plays the model, and can call the MCP for real
 * with the turn's ephemeral token ($question->token), as Claude Code would.
 */
final class FakeAssistantEngine implements AssistantEngine
{
    /** @var list<AssistantQuestion> */
    public array $questions = [];

    /** @param  Closure(AssistantQuestion): AssistantAnswer  $reply */
    public function __construct(private Closure $reply) {}

    public function answer(AssistantQuestion $question): AssistantAnswer
    {
        $this->questions[] = $question;

        return ($this->reply)($question);
    }
}

/** @param  Closure(AssistantQuestion): (AssistantAnswer|string)  $reply */
function fakeAssistant(Closure $reply): FakeAssistantEngine
{
    $engine = new FakeAssistantEngine(function (AssistantQuestion $question) use ($reply) {
        $answer = $reply($question);

        return $answer instanceof AssistantAnswer ? $answer : new AssistantAnswer($answer, 'session-1', 0.05, 1200);
    });
    app()->instance(AssistantEngine::class, $engine);

    return $engine;
}

function enableAssistant(User $admin, array $settings = []): void
{
    $company = $admin->company;
    $company->forceFill(['settings' => [...($company->settings ?? []), 'assistant_enabled' => true, ...$settings]])->save();
}

/** Asks through the web (the turn is queued, not run). */
function askAssistant(User $user, string $text, ?int $conversationId = null): TestResponse
{
    Queue::fake();

    return test()->actingAs($user)->postJson('/api/v1/assistant/messages', array_filter(['text' => $text, 'conversation_id' => $conversationId]));
}

/** What the `assistant` worker does with the queued turn. */
function runAssistantTurn(int $answerId): AssistantMessage
{
    app('auth')->forgetGuards();
    app(AnswerAssistantMessage::class)(AssistantMessage::query()->withoutGlobalScopes()->findOrFail($answerId));

    return AssistantMessage::query()->withoutGlobalScopes()->findOrFail($answerId);
}

function grantPermission(User $admin, string $roleName, Permission $permission): void
{
    $role = roleNamed($admin, $roleName);
    test()->actingAs($admin)->putJson("/api/v1/roles/{$role->id}", ['name' => $role->name, 'permissions' => [...$role->permissionValues(), $permission->value]])->assertOk();
}
