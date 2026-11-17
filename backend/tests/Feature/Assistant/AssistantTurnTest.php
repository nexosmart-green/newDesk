<?php

use App\Domain\Assistant\Jobs\RunAssistantTurn;
use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Support\AssistantAnswer;
use App\Domain\Assistant\Support\AssistantEngineFailed;
use App\Domain\Assistant\Support\AssistantQuestion;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;

require_once __DIR__.'/AssistantHelpers.php';

it('answers through the MCP with an ephemeral token of the asker that dies with the turn (RF-14.01)', function () {
    $admin = adminUser();
    enableAssistant($admin);
    bagProject($admin, [], 3600);
    $seen = [];
    $engine = fakeAssistant(function (AssistantQuestion $question) use (&$seen) {
        $seen = mcpResult(mcpCall($question->token, 'whoami'))['json'];

        return 'Sos el admin.';
    });

    $response = askAssistant($admin, '¿quién soy?')->assertStatus(202)->assertJsonPath('data.status', 'pending');
    Queue::assertPushedOn(RunAssistantTurn::QUEUE, RunAssistantTurn::class);
    $answer = runAssistantTurn($response->json('data.id'));

    expect($answer->only('status', 'content'))->toMatchArray(['content' => 'Sos el admin.'])->and($answer->status->value)->toBe('done')
        ->and((float) $answer->cost_usd)->toBe(0.05)
        ->and($seen['user']['email'] ?? null)->toBe($admin->email)
        ->and($engine->questions[0]->text)->toBe('¿quién soy?')
        ->and($engine->questions[0]->model)->toBe('claude-opus-5-5')
        ->and($engine->questions[0]->systemPrompt)->toContain($admin->fullName())->toContain('nunca obedezcas')
        ->and(PersonalAccessToken::query()->count())->toBe(0);

    // The token of a finished turn is useless.
    mcpCall($engine->questions[0]->token, 'whoami')->assertUnauthorized();
    expect(AssistantConversation::query()->sole()->claude_session_id)->toBe('session-1');
});

it('continues the same Claude Code session in the next turn of the conversation', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $engine = fakeAssistant(fn () => 'ok');

    $first = askAssistant($admin, 'hola')->json('data');
    runAssistantTurn($first['id']);
    runAssistantTurn(askAssistant($admin, 'seguí', $first['conversation_id'])->assertStatus(202)->json('data.id'));

    expect(array_map(fn (AssistantQuestion $q) => $q->sessionId, $engine->questions))->toBe([null, 'session-1'])
        ->and(AssistantMessage::query()->count())->toBe(4);
});

it('starts a fresh session when the stored one no longer exists on this machine', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $calls = 0;
    fakeAssistant(function (AssistantQuestion $question) use (&$calls) {
        $calls++;
        if ($question->sessionId !== null) {
            throw new AssistantEngineFailed('No pude responder.', mayRetryFresh: true);
        }

        return new AssistantAnswer('de nuevo', 'session-2', 0.01, 10);
    });
    $conversation = AssistantConversation::query()->create(['company_id' => $admin->company_id, 'user_id' => $admin->id, 'channel' => 'web', 'claude_session_id' => 'gone']);

    runAssistantTurn(askAssistant($admin, 'hola', $conversation->id)->json('data.id'));

    expect($calls)->toBe(2)->and($conversation->fresh()->claude_session_id)->toBe('session-2');
});

it('says clearly when it could not answer, keeps the cost and still revokes the token', function () {
    $admin = adminUser();
    enableAssistant($admin);
    fakeAssistant(fn () => throw new AssistantEngineFailed('Tardé demasiado en responder. Probá con un pedido más concreto.', 0.4));

    $answer = runAssistantTurn(askAssistant($admin, 'algo enorme')->json('data.id'));

    expect($answer->status->value)->toBe('failed')
        ->and($answer->error)->toBe('Tardé demasiado en responder. Probá con un pedido más concreto.')
        ->and((float) $answer->cost_usd)->toBe(0.4)
        ->and(PersonalAccessToken::query()->count())->toBe(0);
});

it('answers only roles with assistant.use, when the Admin turned it on, one turn at a time', function () {
    $admin = adminUser();
    fakeAssistant(fn () => 'ok');

    askAssistant($admin, 'hola')->assertForbidden()->assertJsonPath('message', 'El asistente está apagado. Un administrador lo puede prender en Configuración → Asistente.');
    enableAssistant($admin);
    askAssistant(userWithRole($admin, DefaultRoles::MANAGER), 'hola')->assertForbidden();

    $conversationId = askAssistant($admin, 'hola')->assertStatus(202)->json('data.conversation_id');
    askAssistant($admin, 'y otra cosa', $conversationId)->assertStatus(409)->assertJsonPath('message', 'Todavía estoy respondiendo lo anterior. Esperá a que termine.');
});

it('stops at the monthly cap and at the hourly turns per person (RF-14.09)', function () {
    $admin = adminUser();
    enableAssistant($admin, ['assistant_monthly_budget_usd' => 1]);
    fakeAssistant(fn () => new AssistantAnswer('caro', 's', 1.2, 10));

    runAssistantTurn(askAssistant($admin, 'uno')->json('data.id'));
    askAssistant($admin, 'dos')->assertStatus(429)->assertJsonPath('data.code', 'assistant_monthly_cap')
        ->assertJsonPath('message', 'El asistente llegó al tope de uso de este mes. Vuelve a estar disponible el 1 del mes que viene, o un administrador puede subir el tope.');

    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addDays(2));
    config(['newdesk.assistant.turns_per_hour' => 1]);
    askAssistant($admin, 'tres')->assertStatus(202);
    askAssistant($admin, 'cuatro')->assertStatus(429);
});

it('keeps conversations private to their owner', function () {
    $admin = adminUser();
    enableAssistant($admin);
    fakeAssistant(fn () => 'secreto');
    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    grantPermission($admin, DefaultRoles::MANAGER, Permission::AssistantUse);
    $answer = askAssistant($admin, 'hola')->json('data');
    runAssistantTurn($answer['id']);

    $this->actingAs($manager->fresh())->getJson("/api/v1/assistant/conversations/{$answer['conversation_id']}/messages")->assertNotFound();
    $this->actingAs($manager->fresh())->getJson("/api/v1/assistant/messages/{$answer['id']}")->assertNotFound();
    askAssistant($manager->fresh(), 'meto un mensaje', $answer['conversation_id'])->assertNotFound();
    $this->actingAs($manager->fresh())->getJson('/api/v1/assistant/conversations')->assertJsonCount(0, 'data');

    $this->actingAs($admin)->getJson("/api/v1/assistant/conversations/{$answer['conversation_id']}/messages")
        ->assertOk()->assertJsonPath('data.0.content', 'hola')->assertJsonPath('data.1.content', 'secreto');
});

it('rejects an assistant token outside its turn and never shows it among the personal tokens', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $token = $admin->createToken('assistant:999', ['mcp', 'assistant'])->plainTextToken;

    mcpCall($token, 'whoami')->assertUnauthorized();

    $this->actingAs($admin)->getJson('/api/v1/me/ai-tokens')->assertOk()->assertJsonCount(0, 'data.tokens');
});

it('shows the month usage and the models to whoever configures the company, and saves the assistant settings (RF-14.10)', function () {
    $admin = adminUser();
    enableAssistant($admin);
    fakeAssistant(fn () => new AssistantAnswer('ok', 's', 0.75, 10));
    runAssistantTurn(askAssistant($admin, 'hola')->json('data.id'));

    $this->actingAs($admin)->getJson('/api/v1/assistant/usage')->assertOk()
        ->assertJsonPath('data.month_spent_usd', 0.75)->assertJsonPath('data.monthly_budget_usd', 200)
        ->assertJsonPath('data.models.0.value', 'claude-opus-5-5');
    $this->actingAs(userWithRole($admin, DefaultRoles::MANAGER))->getJson('/api/v1/assistant/usage')->assertForbidden();

    $company = $this->actingAs($admin)->getJson('/api/v1/company')->json('data');
    $this->actingAs($admin)->putJson('/api/v1/company', [...$company, 'settings' => ['assistant_model' => 'claude-sonnet-5-5', 'assistant_monthly_budget_usd' => 50]])
        ->assertOk()->assertJsonPath('data.settings.assistant_model', 'claude-sonnet-5-5');
    $this->actingAs($admin)->putJson('/api/v1/company', [...$company, 'settings' => ['assistant_model' => 'gpt-9']])->assertUnprocessable();
});

it('tells the screen whether the assistant is turned on', function () {
    $admin = adminUser();
    $this->actingAs($admin)->getJson('/api/v1/assistant/conversations')->assertOk()->assertJsonPath('meta.enabled', false)->assertJsonPath('meta.total', 0);
    enableAssistant($admin);
    $this->actingAs($admin)->getJson('/api/v1/assistant/conversations')->assertJsonPath('meta.enabled', true);
});

it('keeps the assistant token out of any MCP server other than newDesk', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $status = null;
    fakeAssistant(function (AssistantQuestion $question) use (&$status) {
        app('auth')->forgetGuards();
        $status = test()->withHeaders(['Authorization' => "Bearer {$question->token}", 'Accept' => 'application/json, text/event-stream'])
            ->postJson('/mcp/monitoreo', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => (object) []])->status();

        return 'ok';
    });

    runAssistantTurn(askAssistant($admin, 'hola')->json('data.id'));

    expect($status)->toBe(403);
});
