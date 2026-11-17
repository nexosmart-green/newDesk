<?php

use App\Console\Commands\AssistantDiscordCommands;
use App\Domain\Assistant\Actions\AskAssistantFromDiscord;
use App\Domain\Assistant\Actions\ResolveProposalFromDiscord;
use App\Domain\Assistant\Discord\DiscordAnswerMessage;
use App\Domain\Assistant\Jobs\RunAssistantTurn;
use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Assistant\Support\AssistantEngineFailed;
use App\Domain\Assistant\Support\AssistantQuestion;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Monitoring\Actions\SetDiscordUserId;
use App\Domain\Shared\Activity\ActivityLog;
use App\Domain\Shared\Activity\ActivitySource;
use App\Infrastructure\Discord\DiscordInteractionHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/AssistantHelpers.php';

const DISCORD_APP = '900000000000000001';

beforeEach(function () {
    $keys = sodium_crypto_sign_keypair();
    $this->discordSecret = sodium_crypto_sign_secretkey($keys);
    config([
        'newdesk.assistant.discord.application_id' => DISCORD_APP,
        'newdesk.assistant.discord.public_key' => bin2hex(sodium_crypto_sign_publickey($keys)),
    ]);
});

/** Posts an interaction signed as Discord signs it (Ed25519 over timestamp + raw body). */
function discord(array $payload, ?int $timestamp = null, bool $sign = true): TestResponse
{
    app('auth')->forgetGuards();
    $body = json_encode($payload);
    $timestamp ??= now()->timestamp;
    $signature = $sign ? bin2hex(sodium_crypto_sign_detached($timestamp.$body, test()->discordSecret)) : str_repeat('0', 128);

    return test()->call('POST', '/api/discord/interactions', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_SIGNATURE_ED25519' => $signature, 'HTTP_X_SIGNATURE_TIMESTAMP' => (string) $timestamp,
    ], $body);
}

function slash(string $discordUserId, string $sub, array $options): array
{
    return ['type' => 2, 'token' => 'tok-'.$discordUserId, 'member' => ['user' => ['id' => $discordUserId]],
        'data' => ['name' => 'newdesk', 'options' => [['type' => 1, 'name' => $sub,
            'options' => collect($options)->map(fn ($value, $name) => ['type' => 3, 'name' => $name, 'value' => $value])->values()->all()]]]];
}

function button(string $discordUserId, string $customId): array
{
    return ['type' => 3, 'token' => 'btn', 'member' => ['user' => ['id' => $discordUserId]], 'data' => ['custom_id' => $customId]];
}

it('answers only what Discord signed: PING, and 401 for a bad or stale signature', function () {
    discord(['type' => 1])->assertOk()->assertExactJson(['type' => 1]);
    discord(['type' => 1], sign: false)->assertUnauthorized();
    discord(['type' => 1], timestamp: now()->subMinutes(10)->timestamp)->assertUnauthorized();
});

it('explains how to link an unlinked account and runs nothing (CA-14.08)', function () {
    $admin = adminUser();
    enableAssistant($admin);

    discord(slash('111', 'pedir', ['pedido' => 'cargale 20 h a bhn']))->assertOk()
        ->assertJsonPath('type', 4)->assertJsonPath('data.flags', 64)
        ->assertJsonPath('data.content', AskAssistantFromDiscord::NOT_LINKED);
    expect(AssistantMessage::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('links the Discord account with a one-time code from the web, on the same discord_user_id as monitoreo', function () {
    $admin = adminUser();
    $other = userWithRole($admin);
    $other->forceFill(['discord_user_id' => '222'])->save();
    $code = $this->actingAs($admin)->postJson('/api/v1/assistant/discord-link')->assertOk()->json('data.code');

    discord(slash('222', 'vincular', ['codigo' => $code]))->assertJsonPath('data.content',
        'Esa cuenta de Discord ya está vinculada a otra persona de newDesk. Pedile a un administrador que la desvincule.');
    $code = test()->actingAs($admin)->postJson('/api/v1/assistant/discord-link')->json('data.code');
    discord(slash('111', 'vincular', ['codigo' => strtolower($code)]))->assertJsonPath('data.content', fn ($text) => str_starts_with($text, 'Listo: tu Discord quedó vinculado'));

    expect($admin->fresh()->discord_user_id)->toBe('111')
        ->and(ActivityLog::query()->where('event', 'user.discord_id_changed')->exists())->toBeTrue();
    discord(slash('333', 'vincular', ['codigo' => $code]))->assertJsonPath('data.content', fn ($text) => str_starts_with($text, 'Ese código no sirve'));
});

it('asks from Discord, answers by editing the reply, and only the asker can confirm with the button (RF-14.08, CA-14.08)', function () {
    Queue::fake([RunAssistantTurn::class]);
    Http::fake(['discord.com/*' => Http::response([], 200)]);
    $admin = adminUser();
    $admin->forceFill(['discord_user_id' => '111', 'discord_verified_at' => now()])->save();
    enableAssistant($admin);
    ['project' => $project] = bagProject($admin, [], 10 * 3600);
    fakeAssistant(function (AssistantQuestion $question) {
        mcpCall($question->token, 'allocate_hours', ['project' => 'bhn', 'minutes' => 1200, 'reason' => 'Presupuesto #123']);

        return 'Te dejé la propuesta para confirmar.';
    });

    discord(slash('111', 'pedir', ['pedido' => 'cargale 20 h a bhn']))->assertOk()
        ->assertExactJson(['type' => 5, 'data' => ['flags' => 64]]);
    $answer = AssistantMessage::query()->withoutGlobalScopes()->where('role', 'assistant')->sole();
    expect($answer->conversation->channel->value)->toBe('discord');
    runAssistantTurn($answer->id);

    $proposal = AssistantProposal::query()->withoutGlobalScopes()->sole();
    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'PATCH'
        && str_ends_with($request->url(), '/webhooks/'.DISCORD_APP.'/tok-111/messages/@original')
        && str_contains($request['content'], '**Para confirmar:** Cargar 20h 00m a bhn «BHN» — motivo: Presupuesto #123')
        && $request['flags'] === 64 && $request['allowed_mentions'] === ['parse' => []]
        && $request['components'][0]['components'][0]['custom_id'] === "assistant:confirm:{$proposal->id}");

    // Someone else (linked or not) cannot press it.
    userWithRole($admin)->forceFill(['discord_user_id' => '999', 'discord_verified_at' => now()])->save();
    discord(button('999', "assistant:confirm:{$proposal->id}"))->assertJsonPath('data.content', ResolveProposalFromDiscord::NOT_YOURS);
    expect($project->fresh()->allocated_seconds)->toBe(10 * 3600);

    discord(button('111', "assistant:confirm:{$proposal->id}"))->assertOk()->assertJsonPath('type', 7)
        ->assertJsonPath('data.components', [])
        ->assertJsonPath('data.content', fn ($text) => str_contains($text, '**Hecho:** Cargar 20h 00m a bhn'));
    expect($project->fresh()->allocated_seconds)->toBe(30 * 3600)
        ->and(ActivityLog::query()->where('event', 'hours.allocated')->latest('id')->first()->only('source', 'token_name'))
        ->toBe(['source' => ActivitySource::Assistant, 'token_name' => 'discord']);
});

it('says in Discord when the answer failed or the assistant is off, and never leaves it "pensando…"', function () {
    Queue::fake([RunAssistantTurn::class]);
    Http::fake(['discord.com/*' => Http::response([], 200)]);
    $admin = adminUser();
    $admin->forceFill(['discord_user_id' => '111', 'discord_verified_at' => now()])->save();

    discord(slash('111', 'pedir', ['pedido' => 'hola']))->assertJsonPath('data.content', fn ($text) => str_starts_with($text, 'El asistente está apagado'));

    enableAssistant($admin);
    fakeAssistant(fn () => throw new AssistantEngineFailed('Tardé demasiado en responder. Probá con un pedido más concreto.'));
    discord(slash('111', 'pedir', ['pedido' => 'algo enorme']))->assertJsonPath('type', 5);
    runAssistantTurn(AssistantMessage::query()->withoutGlobalScopes()->where('role', 'assistant')->sole()->id);

    Http::assertSent(fn (HttpRequest $request) => $request['content'] === 'Tardé demasiado en responder. Probá con un pedido más concreto.');
});

it('registers /newdesk in the guild with the bot token', function () {
    Http::fake(['discord.com/*' => Http::response([], 200)]);
    config(['newdesk.monitoring.discord.bot_token' => 'bot-secret', 'newdesk.monitoring.discord.guild_id' => '700']);

    $this->artisan('assistant:discord-commands')->assertSuccessful();

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'PUT'
        && $request->url() === 'https://discord.com/api/v10/applications/'.DISCORD_APP.'/guilds/700/commands'
        && $request->hasHeader('Authorization', 'Bot bot-secret')
        && $request->data() === AssistantDiscordCommands::COMMANDS);
});

it('tells the web whether the person is linked to Discord', function () {
    $admin = adminUser();
    $this->actingAs($admin)->getJson('/api/v1/assistant/conversations')->assertJsonPath('meta.discord_linked', false)->assertJsonPath('meta.discord_available', true);
    // An id an Admin typed is not a link the assistant trusts: the button stays offered.
    User::query()->whereKey($admin->id)->update(['discord_user_id' => '111']);
    $this->actingAs($admin->fresh())->getJson('/api/v1/assistant/conversations')->assertJsonPath('meta.discord_linked', false);
    User::query()->whereKey($admin->id)->update(['discord_verified_at' => now()]);
    $this->actingAs($admin->fresh())->getJson('/api/v1/assistant/conversations')->assertJsonPath('meta.discord_linked', true);
});

it('attaches the reports the answer generated to the Discord message', function () {
    Queue::fake([RunAssistantTurn::class]);
    Http::fake(['discord.com/*' => Http::response([], 200)]);
    $admin = adminUser();
    $admin->forceFill(['discord_user_id' => '111', 'discord_verified_at' => now()])->save();
    enableAssistant($admin);
    fakeAssistant(function (AssistantQuestion $question) {
        mcpCall($question->token, 'export_report', ['kind' => 'overdue_tasks', 'format' => 'pdf']);

        return 'Ahí tenés el PDF.';
    });

    discord(slash('111', 'pedir', ['pedido' => 'vencidas en pdf']));
    runAssistantTurn(AssistantMessage::query()->withoutGlobalScopes()->where('role', 'assistant')->sole()->id);

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'PATCH' && $request->isMultipart()
        && collect($request->data())->contains(fn ($part) => str_starts_with((string) ($part['filename'] ?? ''), 'tareas-vencidas-')));
});

it('never acts for an id someone else typed: only a link the person made with a code (RF-14.08)', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    // An Admin of monitoreo puts their own Discord id on someone else.
    app(SetDiscordUserId::class)($admin, '111', $manager);

    discord(slash('111', 'pedir', ['pedido' => 'cargale horas']))->assertJsonPath('data.content', AskAssistantFromDiscord::NOT_LINKED);

    // Verified by a code, then changed by someone else: the trust is gone.
    $code = $this->actingAs($admin)->postJson('/api/v1/assistant/discord-link')->json('data.code');
    discord(slash('111', 'vincular', ['codigo' => $code]));
    expect($admin->fresh()->discord_verified_at)->not->toBeNull();
    app(SetDiscordUserId::class)($admin->fresh(), '444', $manager);
    expect($admin->fresh()->discord_verified_at)->toBeNull();
});

it('keeps one Discord account to one newDesk user across companies', function () {
    $other = adminUser('otra@example.com');
    $other->forceFill(['discord_user_id' => '111', 'discord_verified_at' => now()])->save();
    $admin = adminUser();
    $code = $this->actingAs($admin)->postJson('/api/v1/assistant/discord-link')->json('data.code');

    discord(slash('111', 'vincular', ['codigo' => $code]))->assertJsonPath('data.content', fn ($text) => str_starts_with($text, 'Esa cuenta de Discord ya está vinculada'));
    expect($admin->fresh()->discord_user_id)->toBeNull();
});

it('always shows what a button confirms, however long the answer, and points to the web past five', function () {
    $admin = adminUser();
    $conversation = AssistantConversation::query()->create(['company_id' => $admin->company_id, 'user_id' => $admin->id, 'channel' => 'discord']);
    $answer = $conversation->messages()->create(['company_id' => $admin->company_id, 'role' => 'assistant', 'status' => 'done', 'content' => str_repeat('largo ', 600)]);
    foreach (range(1, 7) as $n) {
        AssistantProposal::query()->create(['company_id' => $admin->company_id, 'user_id' => $admin->id, 'message_id' => $answer->id,
            'tool' => 'allocate_hours', 'arguments' => [], 'summary' => "Cargar {$n}h a bhn", 'status' => 'pending', 'expires_at' => now()->addDay()]);
    }

    $message = DiscordAnswerMessage::of($answer->fresh());

    expect(mb_strlen($message['content']))->toBeLessThanOrEqual(1900)
        ->and($message['content'])->toContain('**Para confirmar:** Cargar 5h a bhn')->not->toContain('Cargar 6h a bhn')
        ->toContain('Hay 2 propuesta(s) más: confirmalas en newDesk → Asistente.')
        ->and($message['components'])->toHaveCount(5);
});

it('never lets the interaction token reach a log through a connection error', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout for https://discord.com/api/v10/webhooks/'.DISCORD_APP.'/SECRET-TOKEN/messages/@original'));

    expect(fn () => app(DiscordInteractionHttpClient::class)->editOriginal('SECRET-TOKEN', 'hola', []))
        ->toThrow(fn (RuntimeException $e) => expect($e->getMessage())->not->toContain('SECRET-TOKEN')->toBe('Discord no respondió (ConnectionException).'));
});
