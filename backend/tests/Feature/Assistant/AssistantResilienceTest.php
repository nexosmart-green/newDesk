<?php

use App\Domain\Assistant\Actions\AbandonAssistantTurn;
use App\Domain\Assistant\Actions\IssueAssistantToken;
use App\Domain\Assistant\Jobs\RunAssistantTurn;
use App\Domain\Assistant\Models\AssistantConversation;
use App\Domain\Assistant\Models\AssistantFile;
use App\Domain\Assistant\Models\AssistantMessage;
use App\Domain\Assistant\Models\AssistantProposal;
use App\Domain\Assistant\Support\AssistantEngineFailed;
use App\Domain\Assistant\Support\AssistantQuestion;
use App\Domain\Identity\DefaultRoles;
use App\Domain\Identity\Enums\Permission;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

require_once __DIR__.'/AssistantHelpers.php';
require_once __DIR__.'/../Tasks/ParityHelpers.php';

it('never retries after a timeout and counts the whole turn budget toward the monthly cap', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $calls = 0;
    fakeAssistant(function () use (&$calls) {
        $calls++;
        throw new AssistantEngineFailed('Tardé demasiado en responder. Probá con un pedido más concreto.', 2.0);
    });
    $conversation = AssistantConversation::query()->create(['company_id' => $admin->company_id, 'user_id' => $admin->id, 'channel' => 'web', 'claude_session_id' => 's']);

    $answer = runAssistantTurn(askAssistant($admin, 'algo pesado', $conversation->id)->json('data.id'));

    expect($calls)->toBe(1)->and((float) $answer->cost_usd)->toBe(2.0)->and($answer->status->value)->toBe('failed');
});

it('closes the turn on any unexpected error: never "pensando…" forever, token revoked', function () {
    $admin = adminUser();
    enableAssistant($admin);
    fakeAssistant(fn () => throw new RuntimeException('disk full'));

    $answer = runAssistantTurn(askAssistant($admin, 'hola')->json('data.id'));

    expect($answer->status->value)->toBe('failed')->and($answer->error)->toBe(AbandonAssistantTurn::UNEXPECTED)
        ->and(PersonalAccessToken::query()->count())->toBe(0);
});

it('closes a turn whose worker was killed (job failed)', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $answer = AssistantMessage::query()->findOrFail(askAssistant($admin, 'hola')->json('data.id'));
    $answer->forceFill(['status' => 'running'])->save();
    app(IssueAssistantToken::class)($admin, $answer);

    (new RunAssistantTurn($answer->id))->failed(new RuntimeException('killed'));

    expect($answer->fresh()->only('status', 'error'))->toMatchArray(['error' => AbandonAssistantTurn::STUCK])
        ->and($answer->fresh()->status->value)->toBe('failed')
        ->and(PersonalAccessToken::query()->count())->toBe(0);
});

it('sweeps stuck turns, expired proposals, expired report files and assistant tokens', function () {
    Storage::fake('local');
    $admin = adminUser();
    enableAssistant($admin);
    fakeAssistant(fn () => 'ok');
    $stuck = AssistantMessage::query()->findOrFail(askAssistant($admin, 'uno')->json('data.id'));
    $stuck->forceFill(['status' => 'running'])->save();
    app(IssueAssistantToken::class)($admin, $stuck);
    $done = runAssistantTurn(askAssistant($admin, 'dos')->json('data.id'));
    $proposal = AssistantProposal::query()->create([
        'company_id' => $admin->company_id, 'user_id' => $admin->id, 'message_id' => $done->id, 'tool' => 'allocate_hours',
        'arguments' => [], 'summary' => 'x', 'status' => 'pending', 'expires_at' => now()->addHour(),
    ]);
    Storage::disk('local')->put('assistant-reports/1/a.xlsx', 'PK');
    $file = AssistantFile::query()->create([
        'uuid' => 'u-1', 'company_id' => $admin->company_id, 'user_id' => $admin->id, 'message_id' => $done->id,
        'name' => 'a.xlsx', 'path' => 'assistant-reports/1/a.xlsx', 'format' => 'xlsx', 'size' => 2, 'expires_at' => now()->addDays(7),
    ]);

    $this->travel(8)->days();
    $this->artisan('assistant:housekeeping')->assertSuccessful();

    expect($stuck->fresh()->status->value)->toBe('failed')
        ->and($done->fresh()->status->value)->toBe('done')
        ->and($proposal->fresh()->status->value)->toBe('expired')
        ->and(AssistantFile::query()->whereKey($file->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists('assistant-reports/1/a.xlsx'))->toBeFalse()
        ->and(PersonalAccessToken::query()->count())->toBe(0);
});

it('reserves the assistant token names, so a personal token never hides from the lists', function () {
    $admin = adminUser();

    $this->actingAs($admin)->postJson('/api/v1/me/ai-tokens', ['name' => 'assistant:1'])
        ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Ese nombre está reservado para el asistente. Elegí otro.');
});

it('stops the downloads of someone whose role lost the assistant', function () {
    $admin = adminUser();
    enableAssistant($admin);
    $manager = userWithRole($admin, DefaultRoles::MANAGER);
    grantPermission($admin, DefaultRoles::MANAGER, Permission::AssistantUse);
    fakeAssistant(function (AssistantQuestion $question) {
        mcpCall($question->token, 'export_report', ['kind' => 'overdue_tasks', 'format' => 'pdf']);

        return 'ok';
    });
    runAssistantTurn(askAssistant($manager->fresh(), 'vencidas en pdf')->json('data.id'));
    $file = AssistantFile::query()->sole();

    revokeModule($admin, DefaultRoles::MANAGER, Permission::AssistantUse);

    $this->actingAs($manager->fresh())->get("/api/v1/assistant/files/{$file->uuid}")->assertForbidden();
});
